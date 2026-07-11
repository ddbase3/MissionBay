<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Dto\AgentBudget;
use AssistantFoundation\Dto\AgentBudgetAssessment;
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Context\AgentContext;
use MissionBay\Orchestrator\Stage\AgentBudgetGuardStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use PHPUnit\Framework\TestCase;

final class AgentBudgetGuardStageTest extends TestCase {

	public function testUnlimitedBudgetRecordsPreflightAssessment(): void {
		$context = $this->createContext(AgentBudget::unlimited(), []);
		$stage = new AgentBudgetGuardStage();

		$this->assertTrue($stage->supports($context));
		$result = $stage->process($context);
		$patch = $result->getPatch();

		$this->assertArrayHasKey('budget', $result->getMetadata());
		$this->assertTrue($result->getMetadata()['budget']['can_continue']);
		$this->assertCount(1, $patch[AgentToolLoopContextKeys::BUDGET_ASSESSMENTS]);
		$assessment = $patch[AgentToolLoopContextKeys::BUDGET_ASSESSMENTS][0];
		$this->assertInstanceOf(AgentBudgetAssessment::class, $assessment);
		$this->assertTrue($assessment->canContinue());
		$this->assertSame(0, $assessment->getAiOperationCount());
	}

	public function testExceededProviderReportedTokenBudgetStopsBeforeNextModelCall(): void {
		$context = $this->createContext(
			new AgentBudget(maxTotalTokens: 10),
			[[
				'operation' => 'chat',
				'usage' => [
					'input_tokens' => 12,
					'output_tokens' => 4,
					'total_tokens' => 16,
					'metrics' => [],
					'details' => []
				]
			]]
		);

		$patch = (new AgentBudgetGuardStage())->process($context)->getPatch();

		$this->assertSame('agent_budget_exceeded', $patch[AgentToolLoopContextKeys::FAILURE_CODE]);
		$this->assertSame(AgentToolLoopContextKeys::PHASE_FAILED, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertSame(16, $patch[AgentToolLoopContextKeys::FAILURE_DETAIL]['exceeded_limits']['total_tokens']['current']);
	}

	public function testStrictBudgetRejectsUnknownUsageInsteadOfTreatingItAsZero(): void {
		$context = $this->createContext(
			new AgentBudget(maxTotalTokens: 100, requireUsageReporting: true),
			[[
				'operation' => 'chat',
				'usage' => [
					'input_tokens' => null,
					'output_tokens' => null,
					'total_tokens' => null,
					'metrics' => [],
					'details' => []
				]
			]]
		);

		$patch = (new AgentBudgetGuardStage())->process($context)->getPatch();

		$this->assertSame('agent_budget_usage_unknown', $patch[AgentToolLoopContextKeys::FAILURE_CODE]);
		$this->assertArrayHasKey('total_tokens', $patch[AgentToolLoopContextKeys::FAILURE_DETAIL]['unknown_limits']);
	}


	public function testToolCheckpointRejectsProjectedCallsBeforeExecution(): void {
		$context = new AgentContext(vars: [
			AgentToolLoopContextKeys::BUDGET => new AgentBudget(maxToolCalls: 2),
			AgentToolLoopContextKeys::MODEL_RESULTS => [],
			AgentToolLoopContextKeys::EXECUTED_TOOL_CALLS => [
				['call_id' => 'call-previous']
			],
			AgentToolLoopContextKeys::PENDING_TOOL_CALLS => [
				new AiToolCall('call-1', 'lookup'),
				new AiToolCall('call-2', 'lookup')
			],
			AgentToolLoopContextKeys::BUDGET_ASSESSMENTS => [],
			AgentToolLoopContextKeys::RUN_STARTED_AT => hrtime(true),
			AgentToolLoopContextKeys::ITERATION => 1,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_TOOLS,
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::FAILURE_CODE => ''
		]);
		$stage = new AgentBudgetGuardStage(
			'tool-budget-guard',
			'tool-budget-guard',
			AgentBudgetGuardStage::CHECKPOINT_TOOLS
		);

		$this->assertTrue($stage->supports($context));
		$patch = $stage->process($context)->getPatch();

		$this->assertSame('agent_budget_exceeded', $patch[AgentToolLoopContextKeys::FAILURE_CODE]);
		$this->assertSame(
			3,
			$patch[AgentToolLoopContextKeys::FAILURE_DETAIL]['exceeded_limits']['tool_calls']['current']
		);
		$this->assertSame(
			2,
			$patch[AgentToolLoopContextKeys::FAILURE_DETAIL]['metadata']['pending_tool_call_count']
		);
		$this->assertSame(
			3,
			$patch[AgentToolLoopContextKeys::FAILURE_DETAIL]['metadata']['projected_tool_call_count']
		);
	}


	public function testFinalCheckpointCanRunAfterTerminalModelDecision(): void {
		$context = $this->createContext(new AgentBudget(maxAiOperations: 2), [[
			'operation' => 'chat',
			'usage' => [
				'input_tokens' => 10,
				'output_tokens' => 5,
				'total_tokens' => 15,
				'metrics' => [],
				'details' => []
			]
		]]);
		$context->setVar(AgentToolLoopContextKeys::PHASE, AgentToolLoopContextKeys::PHASE_FINAL);
		$context->setVar(AgentToolLoopContextKeys::COMPLETED, true);
		$stage = new AgentBudgetGuardStage(
			'final-budget-guard',
			'final-budget-guard',
			AgentBudgetGuardStage::CHECKPOINT_FINAL
		);

		$this->assertTrue($stage->supports($context));
		$this->assertArrayNotHasKey(
			AgentToolLoopContextKeys::FAILURE_CODE,
			$stage->process($context)->getPatch()
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $modelResults
	 */
	private function createContext(AgentBudget $budget, array $modelResults): AgentContext {
		return new AgentContext(vars: [
			AgentToolLoopContextKeys::BUDGET => $budget,
			AgentToolLoopContextKeys::MODEL_RESULTS => $modelResults,
			AgentToolLoopContextKeys::EXECUTED_TOOL_CALLS => [],
			AgentToolLoopContextKeys::BUDGET_ASSESSMENTS => [],
			AgentToolLoopContextKeys::RUN_STARTED_AT => hrtime(true),
			AgentToolLoopContextKeys::ITERATION => 2,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_MODEL,
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::FAILURE_CODE => ''
		]);
	}
}
