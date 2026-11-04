<?php declare(strict_types=1);

namespace MissionBay\Test\ParserService;

use Base3\Logger\Api\ILogger;
use MissionBay\Api\IParserService;
use MissionBay\Dto\AgentContentItem;
use MissionBay\Dto\AgentParsedContent;
use MissionBay\ParserService\ParserServiceTestService;
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

	private function parser(array $options): IParserService {
		$parser = $this->createStub(IParserService::class);
		$parser->method('getOptions')->willReturn($options);
		return $parser;
	}

	private function service(): ParserServiceTestService {
		return new ParserServiceTestService($this->createStub(ILogger::class));
	}
}
