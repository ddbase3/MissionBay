<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace Base3\MissionBay\Test\ParserService;

use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MissionBay\Api\IParserService;
use Base3\MissionBay\Dto\AgentContentItem;
use Base3\MissionBay\Dto\AgentParsedContent;
use Base3\MissionBay\ParserService\ParserServiceTestService;
use PHPUnit\Framework\TestCase;

final class ParserServiceTestServiceBehaviorTest extends TestCase {

	public function testTextInputSuppliesMarkerAndPlainTextToParser(): void {
		$parser = $this->parser(['supported_types' => ['text']]);
		$parser->method('supports')->willReturnCallback(static function(AgentContentItem $item): bool {
			return $item->contentType === 'text/plain' && str_contains((string)$item->content, 'BASE3 parser service test marker');
		});
		$parser->method('parse')->willReturnCallback(static fn(AgentContentItem $item): AgentParsedContent => new AgentParsedContent((string)$item->content));
		$result = $this->service()->test($parser);
		$this->assertSame('text', $result['inputType']);
		$this->assertSame('text', $result['inputName']);
		$this->assertStringContainsString('BASE3 parser service test marker', $result['preview']);
	}

	public function testFileInputIsPreferredOverOtherSupportedTypes(): void {
		$parser = $this->parser(['supported_types' => 'STREAM, text, FILE']);
		$parser->method('supports')->willReturnCallback(static function(AgentContentItem $item): bool {
			return is_array($item->content) && ($item->content['content']['type'] ?? '') === 'file';
		});
		$parser->method('parse')->willReturn(new AgentParsedContent('BASE3 parser service test marker 4f6b21'));
		$result = $this->service()->test($parser);
		$this->assertSame('file', $result['inputType']);
		$this->assertSame('base3-parser-service-test.txt', $result['inputName']);
	}

	public function testStreamPayloadContainsOriginalTestMarker(): void {
		$parser = $this->parser(['supported_types' => ['stream']]);
		$parser->method('supports')->willReturnCallback(static function(AgentContentItem $item): bool {
			return is_resource($item->content) && str_contains((string)stream_get_contents($item->content), 'BASE3 parser service test marker');
		});
		$parser->method('parse')->willReturn(new AgentParsedContent('BASE3 parser service test marker 4f6b21'));
		$this->assertSame('stream', $this->service()->test($parser)['inputType']);
	}

	public function testDefaultsToFileWhenSupportedTypesMissing(): void {
		$parser = $this->parser([]);
		$parser->method('supports')->willReturn(true);
		$parser->method('parse')->willReturn(new AgentParsedContent('BASE3 parser service test marker 4f6b21'));
		$this->assertSame('file', $this->service()->test($parser)['inputType']);
	}

	public function testUnsupportedInputTypesAreRejected(): void {
		$parser = $this->parser(['supported_types' => ['video', 'audio']]);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('requires one supported input type');
		$this->service()->test($parser);
	}

	public function testParserRefusingGeneratedInputIsReported(): void {
		$parser = $this->parser(['supported_types' => ['text']]);
		$parser->method('supports')->willReturn(false);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('does not support its generated test input');
		$this->service()->test($parser);
	}

	public function testEmptyParsedTextIsRejected(): void {
		$parser = $this->parser(['supported_types' => ['text']]);
		$parser->method('supports')->willReturn(true);
		$parser->method('parse')->willReturn(new AgentParsedContent('  '));
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('returned no text');
		$this->service()->test($parser);
	}

	public function testParsedTextMustContainMarker(): void {
		$parser = $this->parser(['supported_types' => ['text']]);
		$parser->method('supports')->willReturn(true);
		$parser->method('parse')->willReturn(new AgentParsedContent('another response'));
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('marker was not found');
		$this->service()->test($parser);
	}

	public function testParserExceptionIsNotSilenced(): void {
		$parser = $this->parser(['supported_types' => ['text']]);
		$parser->method('supports')->willReturn(true);
		$parser->method('parse')->willThrowException(new \RuntimeException('backend unavailable'));
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('backend unavailable');
		$this->service()->test($parser);
	}

	public function testResultIncludesConfiguredIdentities(): void {
		$parser = $this->parser([
			'supported_types' => ['text'],
			'parser_id' => ' parser-one ',
			'service_driver' => ' docling ',
			'connection_id' => ' connector-1 '
		]);
		$parser->method('supports')->willReturn(true);
		$parser->method('parse')->willReturn(new AgentParsedContent('BASE3 parser service test marker 4f6b21'));
		$result = $this->service()->test($parser);
		$this->assertSame('parser-one', $result['serviceId']);
		$this->assertSame('docling', $result['driver']);
		$this->assertSame('connector-1', $result['connectionId']);
		$this->assertTrue($result['durationMs'] >= 0);
	}

	public function testFailureIsLoggedWithoutChangingThrownException(): void {
		$events = [];
		$logger = $this->createStub(ILogger::class);
		$logger->method('error')->willReturnCallback(static function(string|\Stringable $message, array $context = []) use (&$events): void {
			$events[] = [$message, $context];
		});
		$parser = $this->parser(['supported_types' => ['text']]);
		$parser->method('supports')->willReturn(false);
		try {
			(new ParserServiceTestService($logger))->test($parser);
			$this->fail('Expected test failure');
		} catch (\RuntimeException $error) {
			$this->assertStringContainsString('does not support', $error->getMessage());
		}
		$this->assertCount(1, $events);
		$this->assertSame('embedding', $events[0][1]['scope']);
		$this->assertStringContainsString('does not support', $events[0][1]['error']);
	}

	public function testTemporaryFileIsReadableDuringParsingAndRemovedImmediatelyAfterSuccess(): void {
		$path = null;
		$parser = $this->parser(['supported_types' => ['file']]);
		$parser->method('supports')->willReturnCallback(static function(AgentContentItem $item) use (&$path): bool {
			$path = $item->content['content']['meta']['location'] ?? null;
			return is_string($path) && is_file($path);
		});
		$parser->method('parse')->willReturnCallback(static function(AgentContentItem $item) use (&$path): AgentParsedContent {
			self::assertSame($path, $item->content['content']['meta']['file_path']);
			self::assertStringContainsString('BASE3 parser service test marker', (string)file_get_contents($path));
			return new AgentParsedContent('BASE3 parser service test marker 4f6b21');
		});

		$this->assertSame('file', $this->service()->test($parser)['inputType']);
		$this->assertIsString($path);
		$this->assertFileDoesNotExist($path);
	}

	public function testTemporaryFileIsRemovedWhenParserRejectsInput(): void {
		$path = null;
		$parser = $this->parser(['supported_types' => ['file']]);
		$parser->method('supports')->willReturnCallback(static function(AgentContentItem $item) use (&$path): bool {
			$path = $item->content['content']['meta']['location'] ?? null;
			self::assertIsString($path);
			self::assertFileExists($path);
			return false;
		});

		try {
			$this->service()->test($parser);
			$this->fail('Expected unsupported parser input');
		}
		catch(\RuntimeException $e) {
			$this->assertStringContainsString('does not support', $e->getMessage());
		}

		$this->assertIsString($path);
		$this->assertFileDoesNotExist($path);
	}

	public function testTemporaryFileIsRemovedWhenParserThrows(): void {
		$path = null;
		$failure = new \RuntimeException('parser connection interrupted');
		$parser = $this->parser(['supported_types' => ['file']]);
		$parser->method('supports')->willReturn(true);
		$parser->method('parse')->willReturnCallback(static function(AgentContentItem $item) use (&$path, $failure): AgentParsedContent {
			$path = $item->content['content']['meta']['file_path'] ?? null;
			self::assertIsString($path);
			self::assertFileExists($path);
			throw $failure;
		});

		try {
			$this->service()->test($parser);
			$this->fail('Expected parser connection failure');
		}
		catch(\RuntimeException $e) {
			$this->assertSame($failure, $e);
		}

		$this->assertIsString($path);
		$this->assertFileDoesNotExist($path);
	}

	public function testAlreadyRemovedParserInputDoesNotTriggerFurtherDeletion(): void {
		$path = null;
		$parser = $this->parser(['supported_types' => ['file']]);
		$parser->method('supports')->willReturn(true);
		$parser->method('parse')->willReturnCallback(static function(AgentContentItem $item) use (&$path): AgentParsedContent {
			$path = $item->content['content']['meta']['file_path'] ?? null;
			self::assertIsString($path);
			self::assertFileExists($path);
			self::assertTrue(unlink($path));
			return new AgentParsedContent('BASE3 parser service test marker 4f6b21');
		});

		$this->assertSame('file', $this->service()->test($parser)['inputType']);
		$this->assertIsString($path);
		$this->assertFileDoesNotExist($path);
	}

	private function parser(array $options): IParserService {
		$parser = $this->createStub(IParserService::class);
		$parser->method('getOptions')->willReturn($options);
		return $parser;
	}

	private function service(): ParserServiceTestService {
		return new ParserServiceTestService($this->createStub(ILogger::class));
	}
}
