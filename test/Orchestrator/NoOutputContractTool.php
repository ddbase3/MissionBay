<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentToolContractValidation;
use AssistantFoundation\Dto\AiToolCall;
use Base3\Api\IOutputSchemaProvider;
use MissionBay\Api\IAgentTool;
use MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use PHPUnit\Framework\TestCase;

final class NoOutputContractTool implements IAgentTool {

	public static function getName(): string {
		return 'nooutputcontracttool';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'function' => [
				'name' => 'plain',
				'parameters' => ['type' => 'object', 'properties' => []]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		return [];
	}
}
