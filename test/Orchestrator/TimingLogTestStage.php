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
use Base3\AssistantFoundation\Api\IAgentMemory;
use Base3\AssistantFoundation\Api\IAgentStage;
use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Dto\AgentStageResult;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\CoreFoundation\Api\IContainer;
use Base3\MissionBay\MissionBayPlugin;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\AgentStateSynchronizer;
use Base3\MissionBay\Orchestrator\Service\AgentActionResumeService;
use Base3\MissionBay\Orchestrator\Service\AgentBudgetGuardService;
use Base3\MissionBay\Orchestrator\Service\AgentLoopProgressService;
use Base3\MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use Base3\MissionBay\Orchestrator\Suspension\UnavailableAgentSuspensionRepository;
use Base3\MissionBay\Orchestrator\AgentToolOrchestrator;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
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
