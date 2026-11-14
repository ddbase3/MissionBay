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
