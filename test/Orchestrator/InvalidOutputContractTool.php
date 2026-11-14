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
use Base3\AssistantFoundation\Dto\AgentToolContractValidation;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\ChatModel\NormalizedChatModelTrait;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\AgentToolOrchestrator;
use Base3\MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
use Base3\MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use Base3\MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolExecutionStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolObservationStage;
use Base3\MissionBay\Policy\AllowAllAgentActionPolicy;
use PHPUnit\Framework\TestCase;

final class InvalidOutputContractTool implements IAgentTool {

	private int $executions = 0;

	public static function getName(): string {
		return 'invalidoutputcontracttool';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'Contract lookup',
			'outputSchema' => [
				'type' => 'object',
				'required' => ['found'],
				'properties' => [
					'found' => ['type' => 'boolean']
				]
			],
			'function' => [
				'name' => 'contract_lookup',
				'description' => 'Returns a deliberately invalid result for the integration test.',
				'parameters' => [
					'type' => 'object',
					'required' => ['query'],
					'properties' => [
						'query' => ['type' => 'string']
					]
				]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		$this->executions++;

		return ['found' => 1];
	}

	public function getExecutions(): int {
		return $this->executions;
	}
}
