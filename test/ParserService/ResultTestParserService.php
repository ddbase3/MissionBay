<?php declare(strict_types=1);

namespace MissionBay\Test\ParserService;

use AssistantFoundation\Dto\ParserFileRequest;
use MissionBay\Dto\AgentContentItem;
use MissionBay\ParserService\AbstractParserService;
use PHPUnit\Framework\TestCase;

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
