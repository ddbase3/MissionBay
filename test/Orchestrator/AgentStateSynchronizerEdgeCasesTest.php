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

use Base3\AssistantFoundation\Dto\AgentExecutionStatus;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Orchestrator\AgentStateSynchronizer;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys as Keys;
use PHPUnit\Framework\TestCase;

final class AgentStateSynchronizerEdgeCasesTest extends TestCase {

	public function testInitializeTurnSetsTaskAndClampsNegativeMemoryCounts(): void {
		$context = new AgentContext(vars: [AgentStateSynchronizer::CONTEXT_RESULT_KEY => ['stale' => true]]);
		$sync = new AgentStateSynchronizer();
		$sync->initializeTurn($context, 'task-1', 'node-5', 'chat', -3, 8, false);
		$task = $context->getState()->getTask();
		$this->assertSame('task-1', $task->getId());
		$this->assertSame(['mode' => 'chat', 'resume' => false], $task->getInput());
		$this->assertSame(['node_id' => 'node-5'], $task->getMetadata());
		$this->assertSame(0, $context->getState()->getMemory()->getConversationMemoryCount());
		$this->assertSame(8, $context->getState()->getMemory()->getContextContributorCount());
		$this->assertNull($context->getVar(AgentStateSynchronizer::CONTEXT_RESULT_KEY));
	}

	public function testUpdateTaskPreservesIdentifierAndMergesExistingMetadata(): void {
		$context = new AgentContext();
		$sync = new AgentStateSynchronizer();
		$sync->initializeTurn($context, 'task', 'node', 'chat', 0, 0, true);
		$sync->updateTask($context, 'Read status', ['attempts' => 2], ['source' => 'user']);
		$task = $context->getState()->getTask();
		$this->assertSame('task', $task->getId());
		$this->assertSame('Read status', $task->getDescription());
		$this->assertSame(['mode' => 'chat', 'resume' => true, 'attempts' => 2], $task->getInput());
		$this->assertSame(['node_id' => 'node', 'source' => 'user'], $task->getMetadata());
	}

	public function testPlanAndContextDiagnosticsRemainTypedAndOrdered(): void {
		$context = new AgentContext();
		$sync = new AgentStateSynchronizer();
		$sync->initializeTurn($context, 't', 'n', 'chat', 2, 4, false);
		$sync->updatePlan($context, [3 => 'read', 9 => 'write'], ['source' => 'planner']);
		$sync->updateContextContributions($context, [['id' => 'resource', 'content_length' => 7]]);
		$plan = $context->getState()->getPlan();
		$this->assertSame(['read', 'write'], $plan->getSteps());
		$this->assertSame(0, $plan->getCurrentStepIndex());
		$this->assertSame('active', $plan->getStatus());
		$this->assertSame(['source' => 'planner'], $plan->getMetadata());
		$this->assertSame(2, $context->getState()->getMemory()->getConversationMemoryCount());
		$this->assertSame([['id' => 'resource', 'content_length' => 7]], $context->getState()->getMemory()->getContextContributions());
	}

	public function testEmptyPlanHasNoActiveStep(): void {
		$context = new AgentContext();
		(new AgentStateSynchronizer())->updatePlan($context, []);
		$this->assertNull($context->getState()->getPlan()->getCurrentStepIndex());
		$this->assertSame('none', $context->getState()->getPlan()->getStatus());
	}

	public function testSynchronizeRejectsMalformedContextValuesWithoutConvertingToArrays(): void {
		$context = new AgentContext(vars: [
			Keys::ITERATION => -9,
			Keys::CALL_INDEX => '3',
			Keys::MAX_LOOPS => 'not-a-number',
			Keys::OBSERVATIONS => 'invalid',
			Keys::ACTIONS => [['name' => 'read']],
			Keys::FINAL_ASSISTANT_MESSAGE => 'incorrect',
			Keys::FINAL_OUTPUT_CONTENT => ['wrong'],
			Keys::PHASE => Keys::PHASE_MODEL
		]);
		$state = (new AgentStateSynchronizer())->synchronize($context);
		$this->assertSame(AgentExecutionStatus::RUNNING, $state->getExecution()->getStatus());
		$this->assertSame(0, $state->getExecution()->getIteration());
		$this->assertSame(3, $state->getExecution()->getCallIndex());
		$this->assertSame(0, $state->getExecution()->getMaxIterations());
		$this->assertSame([['name' => 'read']], $state->getExecution()->getActions());
		$this->assertSame(null, $state->getResult()->getFinalAssistantMessage());
		$this->assertSame('', $state->getResult()->getFinalOutputContent());
	}

	public function testPendingSuspensionStatusTakesPrecedenceOverFailureCode(): void {
		$context = new AgentContext(vars: [
			Keys::EXECUTION_STATUS => AgentExecutionStatus::AWAITING_APPROVAL,
			Keys::FAILURE_CODE => 'previous_failure',
			Keys::INTERACTION_REQUESTS => [['id' => 'request']],
			Keys::RESUME_HANDLE => 'secret-handle'
		]);
		$state = (new AgentStateSynchronizer())->synchronize($context);
		$this->assertSame(AgentExecutionStatus::AWAITING_APPROVAL, $state->getExecution()->getStatus());
		$this->assertTrue($state->getSuspension()->isSuspended());
		$this->assertSame('secret-handle', $state->getSuspension()->getResumeHandle());
		$this->assertSame([['id' => 'request']], $state->getSuspension()->getInteractionRequests());
	}

	public function testFailureCanBePartialOrFailedDependingOnResponseMode(): void {
		$context = new AgentContext(vars: [Keys::FAILURE_CODE => 'tool_failed', Keys::FINAL_RESPONSE_MODE => Keys::FINAL_RESPONSE_PARTIAL]);
		$sync = new AgentStateSynchronizer();
		$this->assertSame(AgentExecutionStatus::PARTIAL, $sync->synchronize($context)->getExecution()->getStatus());
		$context->setVar(Keys::FINAL_RESPONSE_MODE, Keys::FINAL_RESPONSE_NONE);
		$this->assertSame(AgentExecutionStatus::FAILED, $sync->synchronize($context)->getExecution()->getStatus());
	}

	public function testFinishCreatesTerminalResultAndCompletesActivePlan(): void {
		$context = new AgentContext(vars: [
			Keys::COMPLETED => true,
			Keys::ITERATION => 3,
			Keys::MAX_LOOPS => 10,
			Keys::FINAL_OUTPUT_CONTENT => 'result text',
			Keys::FINAL_RESPONSE_MODE => Keys::FINAL_RESPONSE_COMPLETE
		]);
		$sync = new AgentStateSynchronizer();
		$sync->updatePlan($context, ['step-1', 'step-2']);
		$result = $sync->finish($context);
		$this->assertTrue($context->isFinished());
		$this->assertSame(AgentExecutionStatus::COMPLETED, $result->getStatus());
		$this->assertSame(['content' => 'result text', 'final_response_mode' => Keys::FINAL_RESPONSE_COMPLETE], $result->getOutput());
		$this->assertSame(['iterations' => 3, 'max_iterations' => 10], $result->getMetadata());
		$this->assertSame('completed', $context->getState()->getPlan()->getStatus());
		$this->assertSame(1, $context->getState()->getPlan()->getCurrentStepIndex());
		$this->assertSame($result->toArray(), $context->getVar(AgentStateSynchronizer::CONTEXT_RESULT_KEY));
	}

	public function testFinishWithoutOrchestrationProducesCompletedResult(): void {
		$context = new AgentContext();
		$sync = new AgentStateSynchronizer();
		$sync->updatePlan($context, ['reply']);
		$result = $sync->finishWithoutOrchestration($context);
		$this->assertSame(AgentExecutionStatus::COMPLETED, $result->getStatus());
		$this->assertSame('', $result->getOutput()['content']);
		$this->assertSame(Keys::FINAL_RESPONSE_COMPLETE, $result->getOutput()['final_response_mode']);
		$this->assertSame('completed', $context->getState()->getPlan()->getStatus());
		$this->assertTrue($context->isFinished());
	}
}
