<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp;

use AssistantFoundation\Api\IAgentContext;
use Base3\Api\IClassMap;
use Base3\Logger\Api\ILogger;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentComponentPresetMaterializer;
use MissionBay\Api\IAgentTool;
use MissionBay\Api\IConfirmableAgentTool;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\AgentComponentPresetMaterialization;
use MissionBay\Mcp\McpJsonRpcHandler;
use MissionBay\Mcp\McpToolDefinitionMapper;
use MissionBay\Mcp\McpToolPresetMaterializer;
use MissionBay\Mcp\McpToolProfileRepository;
use MissionBay\Mcp\McpToolResultMapper;
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
