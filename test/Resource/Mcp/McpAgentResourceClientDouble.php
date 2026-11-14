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

namespace Base3\MissionBay\Test\Resource\Mcp;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Api\IMcpClient;
use Base3\MissionBay\Api\IMcpClientFactory;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Dto\Mcp\McpClientConfig;
use Base3\MissionBay\Mcp\Client\McpRemoteToolDefinitionMapper;
use Base3\MissionBay\Mcp\Client\McpRemoteToolResultMapper;
use Base3\MissionBay\Resource\Mcp\McpClientAgentResource;
use PHPUnit\Framework\TestCase;

final class McpAgentResourceClientDouble implements IMcpClient {

	private string $lastToolName = '';

	/** @param array<int,array<string,mixed>>|null $tools */
	public function __construct(private readonly ?array $tools = null) {}

	public function initialize(): array {
		return [
			'protocolVersion' => '2025-11-25',
			'capabilities' => [
				'tools' => [],
				'resources' => [],
				'prompts' => []
			],
			'instructions' => 'Use this test MCP server carefully.',
			'serverInfo' => [
				'name' => 'Test MCP',
				'version' => '1.0.0'
			]
		];
	}

	public function getInitializeResult(): array {
		return $this->initialize();
	}

	public function listTools(): array {
		if($this->tools !== null) {
			return $this->tools;
		}

		return [
			[
				'name' => 'read_file',
				'title' => 'Read file',
				'description' => 'Reads one file.',
				'inputSchema' => ['type' => 'object', 'properties' => []],
				'annotations' => [
					'readOnlyHint' => true,
					'destructiveHint' => false,
					'idempotentHint' => true,
					'openWorldHint' => false
				]
			],
			[
				'name' => 'write_file',
				'title' => 'Write file',
				'description' => 'Writes one file.',
				'inputSchema' => ['type' => 'object', 'properties' => []],
				'outputSchema' => ['type' => 'object'],
				'annotations' => [
					'readOnlyHint' => false,
					'destructiveHint' => true,
					'idempotentHint' => false,
					'openWorldHint' => false
				]
			]
		];
	}

	public function callTool(string $name, array $arguments = []): array {
		$this->lastToolName = $name;
		return [
			'structuredContent' => [
				'remote_tool' => $name,
				'arguments' => $arguments
			],
			'content' => [[
				'type' => 'text',
				'text' => 'done'
			]],
			'isError' => false
		];
	}

	public function listResources(): array {
		return [[
			'uri' => 'repo://readme',
			'name' => 'readme',
			'title' => 'Repository readme',
			'mimeType' => 'text/markdown'
		]];
	}

	public function listResourceTemplates(): array {
		return [[
			'uriTemplate' => 'repo://file/{path}',
			'name' => 'file',
			'title' => 'Repository file'
		]];
	}

	public function readResource(string $uri): array {
		if($uri === 'repo://readme') {
			return [
				'contents' => [[
					'uri' => $uri,
					'mimeType' => 'text/markdown',
					'text' => 'Repository readme'
				]]
			];
		}

		return [
			'contents' => [[
				'uri' => $uri,
				'mimeType' => 'image/png',
				'blob' => 'binary-data'
			]]
		];
	}

	public function listPrompts(): array {
		return [[
			'name' => 'review',
			'description' => 'Reviews one target.',
			'arguments' => [[
				'name' => 'target',
				'required' => true
			]]
		]];
	}

	public function getPrompt(string $name, array $arguments = []): array {
		return [
			'messages' => [[
				'role' => 'user',
				'content' => [
					'type' => 'text',
					'text' => 'Review ' . (string)($arguments['target'] ?? 'target')
				]
			]]
		];
	}

	public function getProtocolVersion(): string {
		return '2025-11-25';
	}

	public function getSessionId(): string {
		return 'session-1';
	}

	public function getLastToolName(): string {
		return $this->lastToolName;
	}
}
