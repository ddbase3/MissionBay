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

namespace Base3\MissionBay\Test\Mcp\Client;

use Base3\MissionBay\Mcp\Client\McpRemoteToolResultMapper;
use Base3\MissionBay\Orchestrator\Validation\JsonSchemaValidator;
use PHPUnit\Framework\TestCase;

final class McpRemoteToolResultMapperTest extends TestCase {

	public function testStructuredResultReturnsStructuredContent(): void {
		$envelope = [
			'content' => [[
				'type' => 'text',
				'text' => '{"width":100}'
			]],
			'structuredContent' => [
				'width' => 100
			],
			'isError' => false
		];

		$result = (new McpRemoteToolResultMapper())->toAgentResult($envelope);

		$this->assertSame(['width' => 100], $result);
		$validation = (new JsonSchemaValidator())->validate($result, [
			'type' => 'object',
			'properties' => [
				'width' => ['type' => 'integer']
			],
			'required' => ['width']
		]);
		$this->assertTrue($validation['valid']);
	}

	public function testNonTextResultWithoutStructuredContentReturnsEnvelope(): void {
		$envelope = [
			'content' => [[
				'type' => 'resource_link',
				'uri' => 'repo://asset'
			]],
			'isError' => false
		];

		$result = (new McpRemoteToolResultMapper())->toAgentResult($envelope);

		$this->assertSame($envelope, $result);
	}

	public function testTextOnlyResultReturnsPlainText(): void {
		$envelope = [
			'content' => [[
				'type' => 'text',
				'text' => 'done'
			]],
			'isError' => false
		];

		$result = (new McpRemoteToolResultMapper())->toAgentResult($envelope);

		$this->assertSame('done', $result);
	}


	public function testErrorLikeTextRemainsNormalResultWithoutProtocolErrorFlag(): void {
		$envelope = [
			'content' => [[
				'type' => 'text',
				'text' => 'Error fetching wiki: repository is not indexed.'
			]]
		];

		$result = (new McpRemoteToolResultMapper())->toAgentResult($envelope);

		$this->assertSame('Error fetching wiki: repository is not indexed.', $result);
	}

	public function testToolExecutionErrorUsesExistingExceptionBoundary(): void {
		$envelope = [
			'content' => [[
				'type' => 'text',
				'text' => 'Remote operation was rejected.'
			]],
			'isError' => true
		];

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Remote operation was rejected.');

		(new McpRemoteToolResultMapper())->toAgentResult($envelope);
	}
}
