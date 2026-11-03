<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AiResultMetadata;
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Api\IAgentCapabilitySourceMetadata;
use MissionBay\Api\IAgentTool;
use MissionBay\Capability\AgentCapabilityCatalogBuilder;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use MissionBay\Orchestrator\Decision\AgentSelectedNativeModelDecisionStrategy;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use PHPUnit\Framework\TestCase;

final class AgentSelectedNativeSourceToolDouble implements IAgentTool, IAgentCapabilitySourceMetadata {

	/** @param array<int,string> $functionNames */
	public function __construct(
		private readonly string $sourceId,
		private readonly string $label,
		private readonly string $description,
		private readonly array $functionNames
	) {}

	public static function getName(): string {
		return 'agentselectednativesourcetooldouble';
	}

	public function getCapabilitySourceId(): string {
		return $this->sourceId;
	}

	public function getCapabilitySourceLabel(): string {
		return $this->label;
	}

	public function getCapabilitySourceDescription(): string {
		return $this->description;
	}

	public function getToolDefinitions(): array {
		$result = [];
		foreach ($this->functionNames as $name) {
			$result[] = [
				'type' => 'function',
				'readOnlyHint' => true,
				'mutation' => false,
				'requiresApproval' => false,
				'function' => [
					'name' => $name,
					'description' => 'Executes ' . $name . '.',
					'parameters' => [
						'type' => 'object',
						'properties' => ['query' => ['type' => 'string']],
						'required' => []
					]
				]
			];
		}
		return $result;
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		return ['ok' => true];
	}
}
