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

namespace Base3\MissionBay\Test\Orchestrator;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Dto\AgentCapabilityCatalog;
use Base3\AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use Base3\AssistantFoundation\Dto\AiChatResult;
use Base3\AssistantFoundation\Dto\AiResultMetadata;
use Base3\AssistantFoundation\Dto\AiToolCall;
use Base3\MissionBay\Api\IAgentCapabilitySourceMetadata;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Capability\AgentCapabilityCatalogBuilder;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use Base3\MissionBay\Orchestrator\Decision\AgentSelectedNativeModelDecisionStrategy;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
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
