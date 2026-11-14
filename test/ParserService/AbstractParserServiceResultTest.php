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
use Base3\MissionBay\ParserService\AbstractParserService;
use PHPUnit\Framework\TestCase;

final class AbstractParserServiceResultTest extends TestCase {

	public function testFileParserResultKeepsNormalizedTextAndNativeStructuredResponse(): void {
		$path = $this->createTemporaryFile('parser input');
		$service = new ResultTestParserService();
		$service->setOptions([
			'supported_extensions' => ['txt'],
		]);

		try {
			$result = $service->parseFile(new ParserFileRequest($path, 'sample.txt'));

			self::assertSame('Hello parser', $result->getText());
			self::assertSame([
				'elements' => [
					['text' => 'Hello parser'],
				]
			], $result->getStructured());
			self::assertSame($result->getStructured(), $result->getRaw());
		} finally {
			@unlink($path);
		}
	}

	public function testAgentParsingKeepsAgentStructuredRootWhileUsingSharedFileParserResult(): void {
		$path = $this->createTemporaryFile('parser input');
		$service = new ResultTestParserService();
		$service->setOptions([
			'content_type' => 'application/x-test-parser',
			'supported_types' => ['file'],
			'supported_extensions' => ['txt'],
		]);
		$item = new AgentContentItem(
			action: 'upsert',
			collectionKey: 'test',
			id: '1',
			hash: 'hash',
			contentType: 'application/x-test-parser',
			content: [
				'content' => [
					'type' => 'file',
					'title' => 'Title',
					'meta' => [
						'file_name' => 'sample.txt',
						'location' => $path,
					]
				]
			],
			isBinary: false,
			size: filesize($path) ?: 0
		);

		try {
			$parsed = $service->parse($item);

			self::assertSame("Title\n\nHello parser", $parsed->text);
			self::assertIsArray($parsed->structured);
			self::assertSame('sample.txt', $parsed->structured['content']['meta']['file_name'] ?? null);
			self::assertSame("Title\n\nHello parser", $parsed->structured['content']['content'] ?? null);
		} finally {
			@unlink($path);
		}
	}

	private function createTemporaryFile(string $content): string {
		$path = tempnam(sys_get_temp_dir(), 'parser_result_test_');
		self::assertIsString($path);
		file_put_contents($path, $content);
		return $path;
	}
}

require_once __DIR__ . '/ResultTestParserService.php';
