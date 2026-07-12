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

namespace MissionBay\Orchestrator;

use AssistantFoundation\Api\IAgentContext;
use MissionBay\Api\IAgentStateContext;
use AssistantFoundation\Dto\AgentBudget;
use AssistantFoundation\Dto\AgentBudgetState;
use AssistantFoundation\Dto\AgentContextWindowState;
use AssistantFoundation\Dto\AgentExecutionState;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentKnowledgeState;
use AssistantFoundation\Dto\AgentMemoryState;
use AssistantFoundation\Dto\AgentPlanState;
use AssistantFoundation\Dto\AgentResult;
use AssistantFoundation\Dto\AgentResultState;
use AssistantFoundation\Dto\AgentState;
use AssistantFoundation\Dto\AgentSuspensionState;
use AssistantFoundation\Dto\AgentTaskState;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;

/**
 * Compatibility bridge between the current MissionBay context-variable loop
 * and the stable AssistantFoundation AgentState/AgentResult model.
 *
 * Existing stages continue to use context keys. After each stage boundary the
 * bridge updates the typed state. Experimental values remain in the bag.
 */
final class AgentStateSynchronizer {

	public const CONTEXT_STATE_KEY = 'agent_state';
	public const CONTEXT_RESULT_KEY = 'agent_result';

	public function initializeTurn(
		IAgentContext $context,
		string $taskId,
		string $nodeId,
		string $mode,
		int $conversationMemoryCount,
		int $contextContributorCount,
		bool $resume
	): void {
		if (!$context instanceof IAgentStateContext) {
			return;
		}

		$state = AgentState::empty()
			->withTask(new AgentTaskState(
				id: $taskId,
				input: [
					'mode' => $mode,
					'resume' => $resume
				],
				metadata: [
					'node_id' => $nodeId
				]
			))
			->withMemory(new AgentMemoryState(
				conversationMemoryCount: max(0, $conversationMemoryCount),
				contextContributorCount: max(0, $contextContributorCount)
			));

		$this->storeState($context, $state);
		$context->forgetVar(self::CONTEXT_RESULT_KEY);
	}

	/**
	 * Stores the minimal normalized task without adding another stage or model call.
	 *
	 * @param array<string,mixed> $input
	 * @param array<string,mixed> $metadata
	 */
	public function updateTask(IAgentContext $context, string $description, array $input = [], array $metadata = []): void {
		if (!$context instanceof IAgentStateContext) {
			return;
		}

		$current = $context->getState()->getTask();
		$task = new AgentTaskState(
			id: $current?->getId() ?? '',
			description: $description,
			input: array_merge($current?->getInput() ?? [], $input),
			metadata: array_merge($current?->getMetadata() ?? [], $metadata)
		);

		$this->storeState($context, $context->getState()->withTask($task));
	}

	/**
	 * Stores the concise execution plan selected by a deliberate orchestrator profile.
	 *
	 * @param array<int,mixed> $steps
	 * @param array<string,mixed> $metadata
	 */
	public function updatePlan(IAgentContext $context, array $steps, array $metadata = []): void {
		if (!$context instanceof IAgentStateContext) {
			return;
		}

		$this->storeState($context, $context->getState()->withPlan(new AgentPlanState(
			steps: array_values($steps),
			currentStepIndex: $steps === [] ? null : 0,
			status: $steps === [] ? 'none' : 'active',
			metadata: $metadata
		)));
	}

	/**
	 * Updates typed memory diagnostics after context contributors were resolved.
	 *
	 * @param array<int,array<string,mixed>> $diagnostics
	 */
	public function updateContextContributions(IAgentContext $context, array $diagnostics): void {
		if (!$context instanceof IAgentStateContext) {
			return;
		}

		$current = $context->getState()->getMemory();
		$memory = new AgentMemoryState(
			conversationMemoryCount: $current?->getConversationMemoryCount() ?? 0,
			contextContributorCount: $current?->getContextContributorCount() ?? 0,
			contextContributions: $diagnostics,
			metadata: $current?->getMetadata() ?? []
		);

		$this->storeState($context, $context->getState()->withMemory($memory));
	}

	public function synchronize(IAgentContext $context): ?AgentState {
		if (!$context instanceof IAgentStateContext) {
			return null;
		}

		$current = $context->getState();
		$status = $this->resolveExecutionStatus($context);
		$budget = $context->getVar(AgentToolLoopContextKeys::BUDGET);
		$state = $current
			->withKnowledge(new AgentKnowledgeState(
				knowledge: $this->readArray($context, 'agent_knowledge'),
				observations: $this->readArray($context, AgentToolLoopContextKeys::OBSERVATIONS)
			))
			->withExecution(new AgentExecutionState(
				status: $status,
				phase: $this->readString($context, AgentToolLoopContextKeys::PHASE),
				iteration: $this->readInt($context, AgentToolLoopContextKeys::ITERATION),
				maxIterations: $this->readInt($context, AgentToolLoopContextKeys::MAX_LOOPS),
				callIndex: $this->readInt($context, AgentToolLoopContextKeys::CALL_INDEX),
				actions: $this->readArray($context, AgentToolLoopContextKeys::ACTIONS),
				actionDecisions: $this->readArray($context, AgentToolLoopContextKeys::ACTION_DECISIONS),
				executedToolCalls: $this->readArray($context, AgentToolLoopContextKeys::EXECUTED_TOOL_CALLS),
				modelResults: $this->readArray($context, AgentToolLoopContextKeys::MODEL_RESULTS),
				stageTrace: $this->readArray($context, AgentToolLoopContextKeys::STAGE_TRACE),
				capabilitySelections: $this->readArray($context, AgentToolLoopContextKeys::CAPABILITY_SELECTIONS),
				toolContractValidations: $this->readArray($context, AgentToolLoopContextKeys::TOOL_CONTRACT_VALIDATIONS),
				toolCacheRecords: $this->readArray($context, AgentToolLoopContextKeys::TOOL_CACHE_RECORDS),
				progressAssessments: $this->readArray($context, AgentToolLoopContextKeys::PROGRESS_ASSESSMENTS)
			))
			->withContextWindow(new AgentContextWindowState(
				assessments: $this->readArray($context, AgentToolLoopContextKeys::CONTEXT_ASSESSMENTS),
				compactions: $this->readArray($context, AgentToolLoopContextKeys::CONTEXT_COMPACTIONS)
			))
			->withBudget(new AgentBudgetState(
				budget: $budget instanceof AgentBudget ? $budget : null,
				assessments: $this->readArray($context, AgentToolLoopContextKeys::BUDGET_ASSESSMENTS)
			))
			->withSuspension(new AgentSuspensionState(
				suspended: AgentExecutionStatus::isSuspended($status),
				status: $status,
				interactionRequests: $this->readArray($context, AgentToolLoopContextKeys::INTERACTION_REQUESTS),
				resumeHandle: $this->readString($context, AgentToolLoopContextKeys::RESUME_HANDLE)
			))
			->withResult(new AgentResultState(
				completed: $context->getVar(AgentToolLoopContextKeys::COMPLETED) === true,
				finalAssistantMessage: $this->readNullableArray($context, AgentToolLoopContextKeys::FINAL_ASSISTANT_MESSAGE),
				finalOutputContent: $this->readString($context, AgentToolLoopContextKeys::FINAL_OUTPUT_CONTENT),
				finalResponseMode: $this->readString($context, AgentToolLoopContextKeys::FINAL_RESPONSE_MODE, AgentToolLoopContextKeys::FINAL_RESPONSE_NONE),
				resultVerifications: $this->readArray($context, AgentToolLoopContextKeys::RESULT_VERIFICATIONS),
				continuationDecisions: $this->readArray($context, AgentToolLoopContextKeys::CONTINUATION_DECISIONS),
				finalResponseInstruction: $this->readString($context, AgentToolLoopContextKeys::FINAL_RESPONSE_INSTRUCTION),
				failureCode: $this->readString($context, AgentToolLoopContextKeys::FAILURE_CODE),
				failureMessage: $this->readString($context, AgentToolLoopContextKeys::FAILURE_MESSAGE),
				failureDetail: $this->readArray($context, AgentToolLoopContextKeys::FAILURE_DETAIL)
			));

		$this->storeState($context, $state);

		return $state;
	}

	public function finish(IAgentContext $context): ?AgentResult {
		if (!$context instanceof IAgentStateContext) {
			return null;
		}

		$state = $this->synchronize($context) ?? $context->getState();
		$plan = $state->getPlan();
		if ($plan !== null && $plan->getStatus() === 'active') {
			$state = $state->withPlan(new AgentPlanState(
				steps: $plan->getSteps(),
				currentStepIndex: $plan->getSteps() === [] ? null : count($plan->getSteps()) - 1,
				status: 'completed',
				metadata: $plan->getMetadata()
			));
			$this->storeState($context, $state);
		}
		$status = $state->getExecution()?->getStatus() ?? AgentExecutionStatus::RUNNING;
		$resultState = $state->getResult();
		$result = new AgentResult(
			status: $status,
			state: $state,
			output: [
				'content' => $resultState?->getFinalOutputContent() ?? '',
				'final_response_mode' => $resultState?->getFinalResponseMode() ?? 'none'
			],
			metadata: [
				'iterations' => $state->getExecution()?->getIteration() ?? 0,
				'max_iterations' => $state->getExecution()?->getMaxIterations() ?? 0
			]
		);

		$context->finish($result);
		$context->setVar(self::CONTEXT_STATE_KEY, $state->toArray());
		$context->setVar(self::CONTEXT_RESULT_KEY, $result->toArray());

		return $result;
	}

	/**
	 * Creates a completed typed result for assistant modes that intentionally
	 * skip tool orchestration.
	 */
	public function finishWithoutOrchestration(IAgentContext $context): ?AgentResult {
		if (!$context instanceof IAgentStateContext) {
			return null;
		}

		$state = $context->getState();
		$plan = $state->getPlan();
		if ($plan !== null && $plan->getStatus() === 'active') {
			$state = $state->withPlan(new AgentPlanState(
				steps: $plan->getSteps(),
				currentStepIndex: $plan->getSteps() === [] ? null : count($plan->getSteps()) - 1,
				status: 'completed',
				metadata: $plan->getMetadata()
			));
		}

		$state = $state
			->withExecution(new AgentExecutionState(
				status: AgentExecutionStatus::COMPLETED,
				phase: AgentToolLoopContextKeys::PHASE_COMPLETE
			))
			->withSuspension(new AgentSuspensionState(
				status: AgentExecutionStatus::COMPLETED
			))
			->withResult(new AgentResultState(
				completed: true,
				finalResponseMode: AgentToolLoopContextKeys::FINAL_RESPONSE_COMPLETE
			));

		$result = new AgentResult(
			status: AgentExecutionStatus::COMPLETED,
			state: $state,
			output: ['content' => '', 'final_response_mode' => AgentToolLoopContextKeys::FINAL_RESPONSE_COMPLETE]
		);
		$context->finish($result);
		$context->setVar(self::CONTEXT_STATE_KEY, $state->toArray());
		$context->setVar(self::CONTEXT_RESULT_KEY, $result->toArray());

		return $result;
	}

	private function storeState(IAgentStateContext $context, AgentState $state): void {
		$context->setState($state);
		$context->setVar(self::CONTEXT_STATE_KEY, $state->toArray());
	}

	private function resolveExecutionStatus(IAgentContext $context): string {
		$status = $this->readString($context, AgentToolLoopContextKeys::EXECUTION_STATUS);
		if (AgentExecutionStatus::isSuspended($status)) {
			return $status;
		}
		if ($this->readString($context, AgentToolLoopContextKeys::FAILURE_CODE) !== '') {
			return $this->readString($context, AgentToolLoopContextKeys::FINAL_RESPONSE_MODE) === AgentToolLoopContextKeys::FINAL_RESPONSE_PARTIAL
				? AgentExecutionStatus::PARTIAL
				: AgentExecutionStatus::FAILED;
		}
		if ($context->getVar(AgentToolLoopContextKeys::COMPLETED) === true) {
			return AgentExecutionStatus::COMPLETED;
		}

		return in_array($status, AgentExecutionStatus::all(), true)
			? $status
			: AgentExecutionStatus::RUNNING;
	}

	/** @return array<int|string,mixed> */
	private function readArray(IAgentContext $context, string $key): array {
		$value = $context->getVar($key);

		return is_array($value) ? $value : [];
	}

	/** @return array<string,mixed>|null */
	private function readNullableArray(IAgentContext $context, string $key): ?array {
		$value = $context->getVar($key);

		return is_array($value) ? $value : null;
	}

	private function readString(IAgentContext $context, string $key, string $default = ''): string {
		$value = $context->getVar($key);

		return is_scalar($value) ? (string)$value : $default;
	}

	private function readInt(IAgentContext $context, string $key): int {
		$value = $context->getVar($key);

		return is_numeric($value) ? max(0, (int)$value) : 0;
	}
}
