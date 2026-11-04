<?php declare(strict_types=1);

namespace MissionBay\Test\ParserService;

use AssistantFoundation\Dto\ParserFileRequest;
use MissionBay\Dto\AgentContentItem;
use MissionBay\ParserService\DoclingParserService;
use PHPUnit\Framework\TestCase;

final class DoclingParserServiceCoverageTest extends TestCase {

	private function text(array $response): string {
		return (string)(new \ReflectionMethod(DoclingParserService::class, 'responseToText'))->invoke(new DoclingParserService(), $response);
	}

	private function item(mixed $content): AgentContentItem {
		return new AgentContentItem('upsert', 'collection', 'item', 'hash', 'text/plain', $content, false, 0);
	}

	public function testNameIdentifiesDoclingParser(): void {
		$this->assertSame('doclingparserservice', DoclingParserService::getName());
	}

	public function testMissingElementsProduceEmptyText(): void {
		$this->assertSame('', $this->text([]));
	}

	public function testNonArrayElementsAreIgnored(): void {
		$this->assertSame('', $this->text(['elements' => 'wrong']));
	}

	public function testArrayElementsWithTextAreJoinedByParagraphs(): void {
		$this->assertSame("First\n\nSecond", $this->text(['elements' => [['text' => ' First '], ['text' => 'Second']]]));
	}

	public function testObjectElementsAreSupported(): void {
		$this->assertSame('Content', $this->text(['elements' => [(object)['text' => 'Content']]]));
	}

	public function testSingleQuotedTextFieldIsExtracted(): void {
		$this->assertSame('Extracted', $this->text(['elements' => [['text' => "TextItem(text='Extracted', orig='Original')"]]]));
	}

	public function testDoubleQuotedTextFieldIsExtracted(): void {
		$this->assertSame('Extracted', $this->text(['elements' => [['text' => 'TextItem(text="Extracted")']]]));
	}

	public function testSingleQuotedOriginalFieldIsUsedWhenTextMissing(): void {
		$this->assertSame('Original', $this->text(['elements' => [['text' => "TextItem(orig='Original')"]]]));
	}

	public function testDoubleQuotedOriginalFieldIsUsedWhenTextMissing(): void {
		$this->assertSame('Original', $this->text(['elements' => [['text' => 'TextItem(orig="Original")']]]));
	}

	public function testPlainTextWithoutWrapperIsPreserved(): void {
		$this->assertSame('Plain text', $this->text(['elements' => [['text' => ' Plain text ']]]));
	}

	public function testUnsupportedValuesDoNotCreateParagraphs(): void {
		$this->assertSame('Valid', $this->text(['elements' => [null, 0, 'raw', ['text' => 42], ['text' => '  '], ['text' => 'Valid']]]));
	}

	public function testTextParsingRequiresExplicitSupportedType(): void {
		$this->assertFalse((new DoclingParserService())->supports($this->item('plain text')));
	}

	public function testConfiguredTextParsingKeepsParserMetadata(): void {
		$parser = new DoclingParserService();
		$parser->setOptions(['supported_types' => ['text']]);
		$parsed = $parser->parse($this->item('  Hello   World '));
		$this->assertSame('Hello World', $parsed->text);
		$this->assertSame('docling', $parsed->metadata['parser']);
		$this->assertSame('text', $parsed->metadata['type']);
	}

	public function testFileSupportRejectsMissingPath(): void {
		$this->assertFalse((new DoclingParserService())->supportsFile(new ParserFileRequest('/nonexistent-docling-' . uniqid(), 'input.pdf')));
	}

	public function testFileExtensionsAreCheckedBeforeNetworkAccess(): void {
		$path = tempnam(sys_get_temp_dir(), 'docling_');
		$this->assertTrue(is_string($path));
		try {
			$parser = new DoclingParserService();
			$parser->setOptions(['supported_extensions' => ['pdf']]);
			$this->assertFalse($parser->supportsFile(new ParserFileRequest($path, 'input.txt')));
			$this->assertTrue($parser->supportsFile(new ParserFileRequest($path, 'input.pdf')));
		} finally {
			@unlink($path);
		}
	}
}
