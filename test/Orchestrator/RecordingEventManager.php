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
use Base3\AssistantFoundation\Api\IAgentStage;
use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Dto\AgentBudgetAssessment;
use Base3\AssistantFoundation\Dto\AgentContextAssessment;
use Base3\AssistantFoundation\Dto\AgentContextCompaction;
use Base3\AssistantFoundation\Dto\AgentExecutionStatus;
use Base3\AssistantFoundation\Dto\AgentResultVerification;
use Base3\AssistantFoundation\Dto\AgentStageTraceEntry;
use Base3\AssistantFoundation\Dto\AgentStageResult;
use Base3\AssistantFoundation\Dto\AgentToolResult;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\MissionBay\ChatModel\NormalizedChatModelTrait;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Dto\Assistant\AgentAssistantTurnResult;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\AgentToolOrchestrator;
use Base3\MissionBay\Orchestrator\AgentToolOrchestratorResult;
use Base3\MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use Base3\MissionBay\Orchestrator\Stage\AgentBudgetGuardStage;
use Base3\MissionBay\Orchestrator\Stage\AgentContextAssessmentStage;
use Base3\MissionBay\Orchestrator\Stage\AgentContextCompactionStage;
use Base3\MissionBay\Orchestrator\Stage\AgentFinalAnswerStage;
use Base3\MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use Base3\MissionBay\Orchestrator\Stage\AgentResultVerificationStage;
use Base3\MissionBay\Orchestrator\Stage\AgentSemanticVerificationStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolExecutionStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolObservationStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use Base3\MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
use Base3\MissionBay\Policy\AllowAllAgentActionPolicy;
use Base3\MissionBay\Service\Assistant\AgentAssistantFinalResponseService;
use Base3\MissionBay\Service\Assistant\AgentAssistantMessageFactory;
use PHPUnit\Framework\TestCase;

final class RecordingEventManager implements IEventManager {

	/** @var array<int,object|string> */
	private array $firedEvents = [];

	public function on(string $event, callable $listener, int $priority = 0): void {
	}

	public function once(string $event, callable $listener, int $priority = 0): void {
	}

	public function off(string $event, callable $listener): void {
	}

	public function fire(object|string $event, ...$args): array {
		$this->firedEvents[] = $event;

		return [];
	}

	/** @return array<int,object|string> */
	public function getFiredEvents(): array {
		return $this->firedEvents;
	}
}
