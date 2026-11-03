<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentToolContractValidation;
use AssistantFoundation\Dto\AiToolCall;
use Base3\Api\IOutputSchemaProvider;
use MissionBay\Api\IAgentTool;
use MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use PHPUnit\Framework\TestCase;

final class ContractTestTool implements IAgentTool, IOutputSchemaProvider {

	public static function getName(): string {
		return 'contracttesttool';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'function' => [
				'name' => 'lookup',
				'description' => 'Looks up a record.',
				'parameters' => [
					'type' => 'object',
					'required' => ['query'],
					'properties' => [
						'query' => ['type' => 'string'],
						'limit' => ['type' => 'integer']
					]
				]
			]
		]];
	}

	public function getOutputSchemas(): array {
		return [
			'lookup' => [
				'type' => 'object',
				'required' => ['found'],
				'properties' => [
					'found' => ['type' => 'boolean']
				]
			]
		];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		return ['found' => true];
	}
}
