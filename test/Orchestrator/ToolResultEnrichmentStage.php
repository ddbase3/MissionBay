<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentBudgetAssessment;
use AssistantFoundation\Dto\AgentContextAssessment;
use AssistantFoundation\Dto\AgentContextCompaction;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentResultVerification;
use AssistantFoundation\Dto\AgentStageTraceEntry;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AgentToolResult;
use Base3\Event\Api\IEventManager;
use MissionBay\ChatModel\NormalizedChatModelTrait;
use MissionBay\Api\IAgentTool;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\Assistant\AgentAssistantTurnResult;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\AgentToolOrchestrator;
use MissionBay\Orchestrator\AgentToolOrchestratorResult;
use MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use MissionBay\Orchestrator\Stage\AgentBudgetGuardStage;
use MissionBay\Orchestrator\Stage\AgentContextAssessmentStage;
use MissionBay\Orchestrator\Stage\AgentContextCompactionStage;
use MissionBay\Orchestrator\Stage\AgentFinalAnswerStage;
use MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use MissionBay\Orchestrator\Stage\AgentResultVerificationStage;
use MissionBay\Orchestrator\Stage\AgentSemanticVerificationStage;
use MissionBay\Orchestrator\Stage\AgentToolExecutionStage;
use MissionBay\Orchestrator\Stage\AgentToolObservationStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
use MissionBay\Policy\AllowAllAgentActionPolicy;
use MissionBay\Service\Assistant\AgentAssistantFinalResponseService;
use MissionBay\Service\Assistant\AgentAssistantMessageFactory;
use PHPUnit\Framework\TestCase;

final class ToolResultEnrichmentStage implements IAgentStage {

	private int $executions = 0;

	public static function getName(): string {
		return 'toolresultenrichmentstage';
	}

	public function id(): string {
		return 'tool-result-enrichment';
	}

	public function name(): string {
		return 'tool-result-enrichment';
	}

	public function getDescription(): string {
		return 'Enriches test tool results.';
	}

	public function getAiUsage(): string {
		return IAgentStage::AI_USAGE_NONE;
	}

	public function supports(IAgentContext $context): bool {
		return $context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_AFTER_TOOLS;
	}

	public function process(IAgentContext $context): AgentStageResult {
		$this->executions++;
		$toolResults = $context->getVar(AgentToolLoopContextKeys::TOOL_RESULTS);
		$enriched = [];

		foreach ($toolResults as $toolResult) {
			if (!$toolResult instanceof AgentToolResult) {
				continue;
			}

			$enriched[] = AgentToolResult::success(
				$toolResult->getCallId(),
				$toolResult->getToolName(),
				$toolResult->getArguments(),
				'stage enrichment',
				$toolResult->getMetadata()
			);
		}

		return AgentStageResult::patch([
			AgentToolLoopContextKeys::TOOL_RESULTS => $enriched
		]);
	}

	public function getExecutions(): int {
		return $this->executions;
	}
}
