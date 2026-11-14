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

use Base3\AssistantFoundation\Dto\ParserFileRequest;
use Base3\MissionBay\Dto\AgentContentItem;
use Base3\MissionBay\ParserService\UnstructuredParserService;
use PHPUnit\Framework\TestCase;

final class UnstructuredParserServiceCoverageTest extends TestCase {

	private function call(string $method, array $response): mixed {
		return (new \ReflectionMethod(UnstructuredParserService::class, $method))->invoke(new UnstructuredParserService(), $response);
	}

	private function item(mixed $content): AgentContentItem {
		return new AgentContentItem('upsert', 'collection', 'item', 'hash', 'text/plain', $content, false, 0);
	}

	public function testNameIdentifiesUnstructuredParser(): void {
		$this->assertSame('unstructuredparserservice', UnstructuredParserService::getName());
	}

	public function testListResponseIsPreservedAsStructuredData(): void {
		$items = [['text' => 'One'], ['text' => 'Two']];
		$this->assertSame($items, $this->call('responseToStructured', $items));
	}

	public function testWrappedElementsAreExtractedAsStructuredData(): void {
		$items = [['text' => 'One']];
		$this->assertSame($items, $this->call('responseToStructured', ['elements' => $items]));
	}

	public function testMissingElementsProduceEmptyStructuredData(): void {
		$this->assertSame([], $this->call('responseToStructured', ['unexpected' => 'value']));
	}

	public function testInvalidElementsProduceEmptyStructuredData(): void {
		$this->assertSame([], $this->call('responseToStructured', ['elements' => null]));
	}

	public function testArrayElementsJoinTextAsParagraphs(): void {
		$this->assertSame("First\n\nSecond", $this->call('responseToText', [['text' => ' First '], ['text' => 'Second']]));
	}

	public function testObjectElementsJoinTextAsParagraphs(): void {
		$this->assertSame("Object\n\nArray", $this->call('responseToText', ['elements' => [(object)['text' => ' Object '], ['text' => 'Array']]]));
	}

	public function testNonStringAndEmptyTextEntriesAreIgnored(): void {
		$this->assertSame('Valid', $this->call('responseToText', ['elements' => ['raw', 10, null, ['text' => 3], ['text' => '  '], ['text' => 'Valid']]]));
	}

	public function testDefaultParserDoesNotSupportUnconfiguredPlainText(): void {
		$this->assertFalse((new UnstructuredParserService())->supports($this->item('plain text')));
	}

	public function testConfiguredParserSupportsPlainTextAndNormalizesWhitespace(): void {
		$parser = new UnstructuredParserService();
		$parser->setOptions(['supported_types' => ['text']]);
		$parsed = $parser->parse($this->item(" First   line\r\nSecond line "));
		$this->assertSame("First line\nSecond line", $parsed->text);
		$this->assertSame('unstructured', $parsed->metadata['parser']);
	}

	public function testFileSupportRejectsUnreadableFile(): void {
		$this->assertFalse((new UnstructuredParserService())->supportsFile(new ParserFileRequest('/no-such-file-' . uniqid(), 'document.pdf')));
	}

	public function testAllowedExtensionsAreNotInferredFromTheTemporaryFilename(): void {
		$path = tempnam(sys_get_temp_dir(), 'unstructured_');
		$this->assertTrue(is_string($path));
		try {
			$parser = new UnstructuredParserService();
			$parser->setOptions(['supported_extensions' => ['pdf']]);
			$this->assertTrue($parser->supportsFile(new ParserFileRequest($path, 'document.pdf')));
			$this->assertFalse($parser->supportsFile(new ParserFileRequest($path, 'document.exe')));
		} finally {
			@unlink($path);
		}
	}
}
