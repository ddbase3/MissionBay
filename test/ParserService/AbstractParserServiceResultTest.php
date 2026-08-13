<?php declare(strict_types=1);

namespace MissionBay\Test\ParserService;

use AssistantFoundation\Dto\ParserFileRequest;
use MissionBay\Dto\AgentContentItem;
use MissionBay\ParserService\AbstractParserService;
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

final class ResultTestParserService extends AbstractParserService {

	public static function getName(): string {
		return 'resulttestparserservice';
	}

	protected function getParserName(): string {
		return 'result-test';
	}

	protected function callParserFile(string $filePath, string $filename): array {
		return [
			'elements' => [
				['text' => 'Hello parser'],
			]
		];
	}

	protected function responseToText(array $response): string {
		return (string)($response['elements'][0]['text'] ?? '');
	}
}
