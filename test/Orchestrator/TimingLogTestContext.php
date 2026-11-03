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
