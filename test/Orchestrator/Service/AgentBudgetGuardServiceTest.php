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

namespace Base3\MissionBay\Test\Orchestrator\Service;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Dto\AgentBudget;
use Base3\MissionBay\Orchestrator\Service\AgentBudgetGuardService;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys as Keys;
use PHPUnit\Framework\TestCase;

final class AgentBudgetGuardServiceTest extends TestCase {

	public function testRejectsUnknownBudgetCheckpoint(): void {
		$this->expectException(\InvalidArgumentException::class);
		(new AgentBudgetGuardService())->check($this->context([]), 'unknown');
	}

	public function testUnlimitedBudgetRecordsNoFailure(): void {
		$result = (new AgentBudgetGuardService())->check($this->context([
			Keys::ITERATION => 2,
			Keys::MODEL_RESULTS => null,
			Keys::EXECUTED_TOOL_CALLS => 'invalid',
			Keys::PENDING_TOOL_CALLS => null,
			Keys::BUDGET_ASSESSMENTS => 'invalid'
		]), AgentBudgetGuardService::CHECKPOINT_MODEL);
		$patch = $result->getPatch();
		$this->assertArrayNotHasKey(Keys::FAILURE_CODE, $patch);
		$this->assertCount(1, $patch[Keys::BUDGET_ASSESSMENTS]);
		$this->assertSame(2, $patch[Keys::BUDGET_ASSESSMENTS][0]->getIteration());
		$this->assertSame(Keys::PHASE_MODEL, $result->getMetadata()['budget']['metadata']['phase']);
	}

	public function testToolBudgetChecksProjectedCallsButExcludesCachedExecutions(): void {
		$context = $this->context([
			Keys::BUDGET => new AgentBudget(maxToolCalls: 2),
			Keys::EXECUTED_TOOL_CALLS => [['cached' => true], ['cached' => false]],
			Keys::PENDING_TOOL_CALLS => ['next-1', 'next-2']
		]);
		$service = new AgentBudgetGuardService();
		$tools = $service->check($context, AgentBudgetGuardService::CHECKPOINT_TOOLS);
		$this->assertSame('agent_budget_exceeded', $tools->getPatch()[Keys::FAILURE_CODE]);
		$assessment = $tools->getPatch()[Keys::BUDGET_ASSESSMENTS][0];
		$this->assertSame(1, $assessment->getToolCallCount());
		$this->assertSame(2, $assessment->getMetadata()['pending_tool_call_count']);
		$this->assertSame(3, $assessment->getMetadata()['projected_tool_call_count']);
		$this->assertSame(1, $assessment->getExceededLimits()['tool_calls']['excess']);
		$this->assertSame(Keys::PHASE_FAILED, $tools->getPatch()[Keys::PHASE]);
		$final = $service->check($context, AgentBudgetGuardService::CHECKPOINT_FINAL);
		$this->assertArrayNotHasKey(Keys::FAILURE_CODE, $final->getPatch());
		$this->assertSame(0, $final->getPatch()[Keys::BUDGET_ASSESSMENTS][0]->getMetadata()['pending_tool_call_count']);
	}

	public function testTokenLimitFailsAtExhaustionAndAggregatesProviderUsage(): void {
		$result = (new AgentBudgetGuardService())->check($this->context([
			Keys::BUDGET => new AgentBudget(maxInputTokens: 10, maxAiOperations: 3),
			Keys::MODEL_RESULTS => [['usage' => ['input_tokens' => '4']], ['usage' => ['input_tokens' => 6]]]
		]), AgentBudgetGuardService::CHECKPOINT_MODEL);
		$this->assertSame('agent_budget_exceeded', $result->getPatch()[Keys::FAILURE_CODE]);
		$assessment = $result->getPatch()[Keys::BUDGET_ASSESSMENTS][0];
		$this->assertSame(10, $assessment->getUsage()->getInputTokens());
		$this->assertSame(2, $assessment->getAiOperationCount());
		$this->assertSame(0, $assessment->getExceededLimits()['input_tokens']['excess']);
		$this->assertArrayNotHasKey('ai_operations', $assessment->getExceededLimits());
	}

	public function testMaxAiOperationCountIsExhaustedAtLimit(): void {
		$result = (new AgentBudgetGuardService())->check($this->context([
			Keys::BUDGET => new AgentBudget(maxAiOperations: 2),
			Keys::MODEL_RESULTS => [['usage' => []], ['unexpected' => true]]
		]), AgentBudgetGuardService::CHECKPOINT_MODEL);
		$this->assertSame('agent_budget_exceeded', $result->getPatch()[Keys::FAILURE_CODE]);
		$this->assertSame(2, $result->getPatch()[Keys::BUDGET_ASSESSMENTS][0]->getExceededLimits()['ai_operations']['current']);
	}

	public function testMissingProviderUsageIsUnknownAndCanBlock(): void {
		$budget = new AgentBudget(maxTotalTokens: 100, requireUsageReporting: true);
		$result = (new AgentBudgetGuardService())->check($this->context([
			Keys::BUDGET => $budget,
			Keys::MODEL_RESULTS => [['usage' => ['total_tokens' => 1]], ['usage' => []]]
		]), AgentBudgetGuardService::CHECKPOINT_FINAL);
		$this->assertSame('agent_budget_usage_unknown', $result->getPatch()[Keys::FAILURE_CODE]);
		$assessment = $result->getPatch()[Keys::BUDGET_ASSESSMENTS][0];
		$this->assertSame(1, $assessment->getUnknownLimits()['total_tokens']['reported_operation_count']);
		$this->assertSame(2, $assessment->getUnknownLimits()['total_tokens']['ai_operation_count']);
	}

	public function testUnknownUsageDoesNotBlockWhenReportingIsOptional(): void {
		$result = (new AgentBudgetGuardService())->check($this->context([
			Keys::BUDGET => new AgentBudget(maxOutputTokens: 100),
			Keys::MODEL_RESULTS => [['message' => 'usage missing']]
		]), AgentBudgetGuardService::CHECKPOINT_MODEL);
		$this->assertArrayNotHasKey(Keys::FAILURE_CODE, $result->getPatch());
		$this->assertArrayHasKey('output_tokens', $result->getPatch()[Keys::BUDGET_ASSESSMENTS][0]->getUnknownLimits());
	}

	public function testMetricLimitsAreSummedOrMarkedUnknownWithoutInventingUsage(): void {
		$budget = new AgentBudget(metricLimits: ['cost' => 0.5, 'images' => 3], requireUsageReporting: true);
		$result = (new AgentBudgetGuardService())->check($this->context([
			Keys::BUDGET => $budget,
			Keys::MODEL_RESULTS => [
				['usage' => ['metrics' => ['cost' => 0.2, 'ignored' => '4']]],
				['usage' => ['metrics' => ['cost' => 0.3]]]
			]
		]), AgentBudgetGuardService::CHECKPOINT_FINAL);
		$this->assertSame('agent_budget_exceeded', $result->getPatch()[Keys::FAILURE_CODE]);
		$assessment = $result->getPatch()[Keys::BUDGET_ASSESSMENTS][0];
		$this->assertSame(0.5, $assessment->getExceededLimits()['metric:cost']['current']);
		$this->assertSame('metric_not_reported', $assessment->getUnknownLimits()['metric:images']['reason']);
		$this->assertArrayNotHasKey('metric:cost', $assessment->getUnknownLimits());
	}

	public function testElapsedLimitUsesRuntimeClockAndDoesNotGoNegative(): void {
		$result = (new AgentBudgetGuardService())->check($this->context([
			Keys::BUDGET => new AgentBudget(maxElapsedMs: 1.0),
			Keys::RUN_STARTED_AT => hrtime(true) - 1_000_000_000
		]), AgentBudgetGuardService::CHECKPOINT_FINAL);
		$this->assertSame('agent_budget_exceeded', $result->getPatch()[Keys::FAILURE_CODE]);
		$this->assertTrue($result->getPatch()[Keys::BUDGET_ASSESSMENTS][0]->getElapsedMs() >= 1.0);
	}

	/** @param array<string,mixed> $values */
	private function context(array $values): IAgentContext {
		$context = $this->createStub(IAgentContext::class);
		$context->method('getVar')->willReturnCallback(static fn(string $key): mixed => $values[$key] ?? null);
		return $context;
	}
}
