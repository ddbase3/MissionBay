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

final class TimingLogTestContext implements IAgentContext {

	private array $values = [];

	public static function getName(): string {
		return 'timinglogtestcontext';
	}

	public function getMemory(): IAgentMemory {
		throw new \LogicException('Memory is not used by this test.');
	}

	public function setMemory(IAgentMemory $memory): void {
		throw new \LogicException('Memory is not used by this test.');
	}

	public function setVar(string $key, mixed $value): void {
		$this->values[$key] = $value;
	}

	public function getVar(string $key): mixed {
		return $this->values[$key] ?? null;
	}

	public function forgetVar(string $key): void {
		unset($this->values[$key]);
	}

	public function listVars(): array {
		return array_keys($this->values);
	}
}
