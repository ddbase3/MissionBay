<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Dto\AgentBudget;
use AssistantFoundation\Dto\AgentExecutionStatus;
use MissionBay\Context\AgentContext;
use MissionBay\Orchestrator\AgentStateSynchronizer;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use PHPUnit\Framework\TestCase;

final class AgentStateSynchronizerTest extends TestCase {

	public function testSynchronizerProjectsStableContextKeysIntoTypedState(): void {
		$context = new AgentContext();
		$context->setVar('experimental.stage.value', ['keep' => true]);

		$synchronizer = new AgentStateSynchronizer();
		$synchronizer->initializeTurn(
			context: $context,
			taskId: 'turn-1',
			nodeId: 'assistant',
			mode: 'chat',
			conversationMemoryCount: 2,
			contextContributorCount: 1,
			resume: false
		);
		$synchronizer->updateContextContributions($context, [[
			'id' => 'prefs',
			'content_length' => 25
		]]);

		$context->setVar(AgentToolLoopContextKeys::EXECUTION_STATUS, AgentExecutionStatus::COMPLETED);
		$context->setVar(AgentToolLoopContextKeys::PHASE, AgentToolLoopContextKeys::PHASE_COMPLETE);
		$context->setVar(AgentToolLoopContextKeys::ITERATION, 2);
		$context->setVar(AgentToolLoopContextKeys::MAX_LOOPS, 6);
		$context->setVar(AgentToolLoopContextKeys::COMPLETED, true);
		$context->setVar(AgentToolLoopContextKeys::FINAL_OUTPUT_CONTENT, 'Done.');
		$context->setVar(AgentToolLoopContextKeys::FINAL_RESPONSE_MODE, AgentToolLoopContextKeys::FINAL_RESPONSE_COMPLETE);
		$context->setVar(AgentToolLoopContextKeys::BUDGET, AgentBudget::fromArray(['max_tool_calls' => 4]));
		$context->setVar(AgentToolLoopContextKeys::OBSERVATIONS, [['tool' => 'lookup', 'ok' => true]]);

		$result = $synchronizer->finish($context);

		$this->assertNotNull($result);
		$this->assertTrue($context->isFinished());
		$this->assertSame('turn-1', $result->getState()->getTask()?->getId());
		$this->assertSame(2, $result->getState()->getExecution()?->getIteration());
		$this->assertSame(4, $result->getState()->getBudget()?->getBudget()?->getMaxToolCalls());
		$this->assertTrue($result->getState()->getKnowledge()?->getObservations()[0]['ok']);
		$this->assertSame(['keep' => true], $context->getVar('experimental.stage.value'));
		$this->assertIsArray($context->getVar(AgentStateSynchronizer::CONTEXT_STATE_KEY));
		$this->assertIsArray($context->getVar(AgentStateSynchronizer::CONTEXT_RESULT_KEY));
	}

	public function testMinimalTaskNormalizationReusesExistingTaskState(): void {
		$context = new AgentContext();
		$synchronizer = new AgentStateSynchronizer();
		$synchronizer->initializeTurn($context, 'turn-1', 'assistant', 'chat', 1, 0, false);
		$synchronizer->updateTask(
			$context,
			'Use the selected option from the previous turn.',
			[
				'prompt' => '2.',
				'follow_up' => true,
				'completion_criteria' => ['Answer directly.']
			],
			['normalization' => 'deterministic-v1']
		);

		$task = $context->getState()->getTask();
		$this->assertSame('turn-1', $task?->getId());
		$this->assertSame('Use the selected option from the previous turn.', $task?->getDescription());
		$this->assertTrue($task?->getInput()['follow_up']);
		$this->assertSame('assistant', $task?->getMetadata()['node_id']);
		$this->assertSame('deterministic-v1', $task?->getMetadata()['normalization']);
	}


	public function testDeliberatePlanReusesExistingPlanState(): void {
		$context = new AgentContext();
		$synchronizer = new AgentStateSynchronizer();
		$synchronizer->initializeTurn($context, 'turn-plan', 'assistant', 'chat', 2, 0, false);
		$synchronizer->updatePlan($context, [
			['id' => 'assess', 'label' => 'Identify missing evidence'],
			['id' => 'answer', 'label' => 'Answer directly']
		], [
			'source' => 'orchestrator-profile',
			'verification' => 'semantic-verification'
		]);

		$plan = $context->getState()->getPlan();
		$this->assertSame('active', $plan?->getStatus());
		$this->assertCount(2, $plan?->getSteps() ?? []);
		$this->assertSame('orchestrator-profile', $plan?->getMetadata()['source']);

		$synchronizer->finishWithoutOrchestration($context);
		$this->assertSame('completed', $context->getState()->getPlan()?->getStatus());
	}

	public function testNewTurnStartsWithFreshStableState(): void {
		$context = new AgentContext();
		$synchronizer = new AgentStateSynchronizer();

		$synchronizer->initializeTurn($context, 'turn-1', 'assistant', 'chat', 0, 0, false);
		$context->setVar(AgentToolLoopContextKeys::COMPLETED, true);
		$synchronizer->finish($context);

		$synchronizer->initializeTurn($context, 'turn-2', 'assistant', 'chat', 1, 2, false);

		$this->assertFalse($context->isFinished());
		$this->assertNull($context->getVar(AgentStateSynchronizer::CONTEXT_RESULT_KEY));
		$this->assertSame('turn-2', $context->getState()->getTask()?->getId());
		$this->assertNull($context->getState()->getExecution());
		$this->assertSame(2, $context->getState()->getMemory()?->getContextContributorCount());
	}
}
