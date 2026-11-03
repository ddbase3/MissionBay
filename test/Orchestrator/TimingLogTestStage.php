<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentMemory;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentStageResult;
use Base3\Logger\Api\ILogger;
use Base3\Api\IContainer;
use MissionBay\MissionBayPlugin;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\AgentStateSynchronizer;
use MissionBay\Orchestrator\Service\AgentActionResumeService;
use MissionBay\Orchestrator\Service\AgentBudgetGuardService;
use MissionBay\Orchestrator\Service\AgentLoopProgressService;
use MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use MissionBay\Orchestrator\Suspension\UnavailableAgentSuspensionRepository;
use MissionBay\Orchestrator\AgentToolOrchestrator;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use PHPUnit\Framework\TestCase;

final class TimingLogTestStage implements IAgentStage {

	public function __construct(private bool $fails = false) {}

	public static function getName(): string {
		return 'timinglogteststage';
	}

	public function id(): string {
		return 'timing-test-stage';
	}

	public function name(): string {
		return 'timing-test-stage';
	}

	public function getDescription(): string {
		return 'Stage for timing log regression tests.';
	}

	public function getAiUsage(): string {
		return self::AI_USAGE_CONDITIONAL;
	}

	public function supports(IAgentContext $context): bool {
		return true;
	}

	public function process(IAgentContext $context): AgentStageResult {
		if ($this->fails) {
			throw new \RuntimeException('timing stage failure');
		}

		return AgentStageResult::patch([
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FINAL,
			AgentToolLoopContextKeys::COMPLETED => true,
			AgentToolLoopContextKeys::MODEL_RESULTS => [[
				'duration_ms' => 31.25,
				'extra' => ['secret' => 'private model metadata']
			]]
		], ['secret' => 'private stage metadata']);
	}
}
