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

namespace Base3\MissionBay\Test\Mcp;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IAgentComponentPresetMaterializer;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Api\IConfirmableAgentTool;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Dto\AgentComponentPresetMaterialization;
use Base3\MissionBay\Mcp\McpJsonRpcHandler;
use Base3\MissionBay\Mcp\McpToolDefinitionMapper;
use Base3\MissionBay\Mcp\McpToolPresetMaterializer;
use Base3\MissionBay\Mcp\McpToolProfileRepository;
use Base3\MissionBay\Mcp\McpToolResultMapper;
use PHPUnit\Framework\TestCase;

final class ClientAuthorizedToolTestDouble implements IAgentTool, IConfirmableAgentTool {

	private int $callCount = 0;
	private int $confirmationRequestCount = 0;

	public static function getName(): string {
		return 'clientauthorizedtooltestdouble';
	}

	public function getToolDefinitions(): array {
		return [
			[
				'type' => 'function',
				'label' => 'List plugins',
				'readOnlyHint' => true,
				'function' => [
					'name' => 'list_plugins',
					'description' => 'Lists plugins.',
					'parameters' => ['type' => 'object', 'properties' => []]
				]
			],
			[
				'type' => 'function',
				'label' => 'Set plugin state',
				'readOnlyHint' => false,
				'mutation' => true,
				'requiresApproval' => true,
				'function' => [
					'name' => 'set_plugin_state',
					'description' => 'Changes plugin state.',
					'parameters' => [
						'type' => 'object',
						'properties' => [
							'plugin' => ['type' => 'string'],
							'state' => ['type' => 'string']
						],
						'required' => ['plugin', 'state']
					]
				]
			]
		];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		$this->callCount++;

		if($name === 'list_plugins') {
			return ['plugins' => []];
		}

		if($name === 'set_plugin_state') {
			return [
				'ok' => true,
				'plugin' => (string)($arguments['plugin'] ?? ''),
				'state' => (string)($arguments['state'] ?? '')
			];
		}

		throw new \InvalidArgumentException('Unsupported test tool: ' . $name);
	}

	public function getConfirmationRequest(string $name, array $arguments, IAgentContext $context): ?array {
		$this->confirmationRequestCount++;

		return [
			'title' => 'Confirm mutation',
			'message' => 'Confirm the mutation.'
		];
	}

	public function getCallCount(): int {
		return $this->callCount;
	}

	public function getConfirmationRequestCount(): int {
		return $this->confirmationRequestCount;
	}
}
