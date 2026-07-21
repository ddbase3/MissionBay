<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentActionDecision;
use AssistantFoundation\Dto\AgentBudget;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentInteractionRequest;
use AssistantFoundation\Dto\AgentInteractionResponse;
use AssistantFoundation\Dto\AgentResume;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AgentSuspension;
use AssistantFoundation\Dto\AgentToolCacheConfig;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantFoundation\Dto\AiToolCall;
use Base3\Event\Api\IEventManager;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentTool;
use MissionBay\Audit\AgentToolAuditContext;
use MissionBay\Cache\AgentToolCacheKeyBuilder;
use MissionBay\Cache\NullAgentToolResultCache;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\Policy\IAgentActionPolicyResolver;
use MissionBay\Orchestrator\Service\AgentActionResumeService;
use MissionBay\Orchestrator\Service\AgentActionReviewService;
use MissionBay\Orchestrator\Service\AgentBudgetGuardService;
use MissionBay\Orchestrator\Service\AgentCapabilitySelectionGuardService;
use MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use MissionBay\Orchestrator\Service\AgentResultVerificationService;
use MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use MissionBay\Orchestrator\Service\AgentToolResultCacheService;
use MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use MissionBay\Orchestrator\Stage\AgentToolExecutionStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;

/**
 * Executes preset tool tests through the real action-policy and execution path.
 *
 * Mutations therefore use durable approval, exact action fingerprints,
 * pre-commit validation, audit events, and input/output contract validation.
 * Tool-result caching is deliberately disabled so every accepted test performs
 * a real invocation.
 */
final class AgentComponentPresetToolTestService {

	private AgentActionPolicyStage $actionPolicyStage;
	private AgentToolExecutionStage $toolExecutionStage;

	public function __construct(
		IAgentActionPolicyResolver $policyResolver,
		AgentActionFingerprint $fingerprint,
		AgentActionReviewService $actionReviewService,
		private readonly AgentActionResumeService $actionResumeService,
		AgentToolContractValidationService $toolContractValidationService,
		AgentCapabilitySelectionGuardService $capabilitySelectionGuardService,
		AgentMutationCommitGuardService $mutationCommitGuardService,
		IEventManager $eventManager,
		AgentToolDefinitionSemantics $toolDefinitionSemantics
	) {
		$this->actionPolicyStage = new AgentActionPolicyStage(
			$policyResolver,
			$fingerprint,
			'component-preset-test-action-policy',
			'component-preset-test-action-policy',
			['mutation-approval-actions', 'allow-all-actions'],
			$actionReviewService,
			$toolContractValidationService,
			$capabilitySelectionGuardService
		);

		$cacheService = new AgentToolResultCacheService(
			new NullAgentToolResultCache(),
			$eventManager,
			new AgentToolCacheKeyBuilder(),
			$mutationCommitGuardService,
			$toolContractValidationService
		);

		$this->toolExecutionStage = new AgentToolExecutionStage(
			$eventManager,
			'component-preset-test-execution',
			'component-preset-test-execution',
			$cacheService,
			new AgentBudgetGuardService(),
			new AgentResultVerificationService(),
			$mutationCommitGuardService,
			$toolContractValidationService,
			$capabilitySelectionGuardService
		);

		$this->toolDefinitionSemantics = $toolDefinitionSemantics;
	}

	private readonly AgentToolDefinitionSemantics $toolDefinitionSemantics;

	/** @param array<string,mixed> $arguments @return array<string,mixed> */
	public function invoke(
		IAgentTool $tool,
		string $functionName,
		array $arguments,
		IAgentContext $context
	): array {
		$functionName = trim($functionName);

		if($functionName === '') {
			return $this->failure('Missing function name.');
		}

		$definitions = $this->normalizeDefinitions($tool->getToolDefinitions());

		if(!$this->hasFunction($definitions, $functionName)) {
			return $this->failure('Function not found on materialized preset tool: ' . $functionName);
		}

		$call = new AiToolCall(
			AgentToolAuditContext::generateCallId('preset-test-call'),
			$functionName,
			$arguments,
			['source' => 'agent-component-preset-test']
		);

		$this->initializeContext($context, $tool, $definitions, [$call]);
		$this->apply($context, $this->actionPolicyStage->process($context));

		if($context->getVar(AgentToolLoopContextKeys::SUSPENDED) === true) {
			return $this->buildInteractionResponse($context);
		}

		if($this->hasFailure($context)) {
			return $this->buildFailureResponse($context);
		}

		if($this->hasPendingCalls($context)) {
			$this->apply($context, $this->toolExecutionStage->process($context));
		}

		return $this->buildExecutionResponse($context);
	}

	/** @return array<string,mixed> */
	public function resume(
		IAgentTool $tool,
		string $resumeHandle,
		string $requestId,
		string $decision,
		string $note,
		IAgentContext $context
	): array {
		$decision = strtolower(trim($decision));

		if(!in_array($decision, [AgentInteractionResponse::DECISION_APPROVE, AgentInteractionResponse::DECISION_DENY], true)) {
			return $this->failure('Invalid confirmation decision.');
		}

		try {
			$resume = new AgentResume(
				trim($resumeHandle),
				[
					new AgentInteractionResponse(
						trim($requestId),
						$decision,
						[],
						trim($note),
						['source' => 'agent-component-preset-test']
					)
				]
			);
			$prepared = $this->actionResumeService->prepare($resume);
		}
		catch(\Throwable $e) {
			return $this->failure($e->getMessage(), $e::class);
		}

		$definitions = $this->normalizeDefinitions($tool->getToolDefinitions());
		$this->initializeContext($context, $tool, $definitions, []);
		$this->restoreSuspensionState($context, $prepared->getSuspension());
		$context->setVar(AgentToolLoopContextKeys::RESUME, $prepared);
		$context->setVar(AgentToolLoopContextKeys::PHASE, AgentToolLoopContextKeys::PHASE_RESUME);
		$this->apply($context, $this->actionResumeService->resume($context));

		if($this->hasFailure($context)) {
			return $this->buildFailureResponse($context);
		}

		if($this->hasPendingCalls($context)) {
			$this->apply($context, $this->actionPolicyStage->process($context));
		}

		if($context->getVar(AgentToolLoopContextKeys::SUSPENDED) === true) {
			return $this->buildInteractionResponse($context);
		}

		if($this->hasFailure($context)) {
			return $this->buildFailureResponse($context);
		}

		if($this->hasPendingCalls($context)) {
			$this->apply($context, $this->toolExecutionStage->process($context));
		}

		return $this->buildExecutionResponse($context);
	}

	/**
	 * @param array<int,array<string,mixed>> $definitions
	 * @param array<int,AiToolCall> $pendingCalls
	 */
	private function initializeContext(
		IAgentContext $context,
		IAgentTool $tool,
		array $definitions,
		array $pendingCalls
	): void {
		$nodeId = $tool instanceof IAgentResource ? $tool->getId() : 'component_preset_test';
		$values = [
			AgentToolLoopContextKeys::TOOL_DEFINITIONS => $definitions,
			AgentToolLoopContextKeys::MUTATION_TOOL_NAMES => $this->toolDefinitionSemantics->getMutationToolNames($definitions),
			AgentToolLoopContextKeys::TOOLS => [$tool],
			AgentToolLoopContextKeys::PENDING_TOOL_CALLS => $pendingCalls,
			AgentToolLoopContextKeys::MESSAGES => [],
			AgentToolLoopContextKeys::ACTIONS => [],
			AgentToolLoopContextKeys::ACTION_DECISIONS => [],
			AgentToolLoopContextKeys::ACTION_REVIEW_CANDIDATES => [],
			AgentToolLoopContextKeys::PREAPPROVED_ACTIONS => [],
			AgentToolLoopContextKeys::INTERACTION_REQUESTS => [],
			AgentToolLoopContextKeys::SUSPENSION => null,
			AgentToolLoopContextKeys::RESUME_HANDLE => '',
			AgentToolLoopContextKeys::RESUME => null,
			AgentToolLoopContextKeys::SUSPENDED => false,
			AgentToolLoopContextKeys::EXECUTION_STATUS => AgentExecutionStatus::RUNNING,
			AgentToolLoopContextKeys::TOOL_RESULTS => [],
			AgentToolLoopContextKeys::TOOL_CONTRACT_VALIDATIONS => [],
			AgentToolLoopContextKeys::OBSERVATIONS => [],
			AgentToolLoopContextKeys::EXECUTED_TOOL_CALLS => [],
			AgentToolLoopContextKeys::TOOL_CALL_INDEXES => [],
			AgentToolLoopContextKeys::TOOL_CACHE_PLANS => [],
			AgentToolLoopContextKeys::TOOL_CACHE_RECORDS => [],
			AgentToolLoopContextKeys::BUDGET => AgentBudget::unlimited(),
			AgentToolLoopContextKeys::BUDGET_ASSESSMENTS => [],
			AgentToolLoopContextKeys::TOOL_CACHE_CONFIG => AgentToolCacheConfig::disabled(),
			AgentToolLoopContextKeys::MODEL_RESULTS => [],
			AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS => [],
			AgentToolLoopContextKeys::RESULT_VERIFICATIONS => [],
			AgentToolLoopContextKeys::CAPABILITY_SELECTION_APPLIED => false,
			AgentToolLoopContextKeys::SELECTED_TOOL_NAMES => [],
			AgentToolLoopContextKeys::EVENT_CALLBACK => null,
			AgentToolLoopContextKeys::LOGGER => null,
			AgentToolLoopContextKeys::NODE_ID => $nodeId,
			AgentToolLoopContextKeys::TRACE => [
				'source' => 'agent-component-preset-test',
				'node_id' => $nodeId,
				'turn_id' => AgentToolAuditContext::generateCallId('preset-test-turn')
			],
			AgentToolLoopContextKeys::RUN_STARTED_AT => hrtime(true),
			AgentToolLoopContextKeys::ITERATION => 1,
			AgentToolLoopContextKeys::CALL_INDEX => 0,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_TOOLS,
			AgentToolLoopContextKeys::FINAL_RESPONSE_MODE => AgentToolLoopContextKeys::FINAL_RESPONSE_NONE,
			AgentToolLoopContextKeys::FAILURE_CODE => '',
			AgentToolLoopContextKeys::FAILURE_MESSAGE => '',
			AgentToolLoopContextKeys::FAILURE_DETAIL => [],
			AgentToolLoopContextKeys::COMPLETED => false
		];

		foreach($values as $key => $value) {
			$context->setVar($key, $value);
		}
	}

	private function restoreSuspensionState(IAgentContext $context, AgentSuspension $suspension): void {
		$state = $suspension->getState();
		$context->setVar(AgentToolLoopContextKeys::ITERATION, max(0, (int)($state['iteration'] ?? 0)));
		$context->setVar(AgentToolLoopContextKeys::CALL_INDEX, max(0, (int)($state['call_index'] ?? 0)));
		$context->setVar(AgentToolLoopContextKeys::MESSAGES, is_array($state['messages'] ?? null) ? $state['messages'] : []);
		$context->setVar(AgentToolLoopContextKeys::PENDING_TOOL_CALLS, $this->restoreToolCalls($state['pending_tool_calls'] ?? []));
		$context->setVar(AgentToolLoopContextKeys::ACTIONS, $this->restoreActions($state['actions'] ?? []));
		$context->setVar(AgentToolLoopContextKeys::ACTION_DECISIONS, $this->restoreActionDecisions($state['action_decisions'] ?? []));
		$context->setVar(AgentToolLoopContextKeys::TOOL_RESULTS, $this->restoreToolResults($state['tool_results'] ?? []));
		$context->setVar(AgentToolLoopContextKeys::OBSERVATIONS, $this->restoreToolResults($state['observations'] ?? []));
		$context->setVar(AgentToolLoopContextKeys::EXECUTED_TOOL_CALLS, is_array($state['executed_tool_calls'] ?? null) ? $state['executed_tool_calls'] : []);
		$context->setVar(AgentToolLoopContextKeys::TOOL_CALL_INDEXES, is_array($state['tool_call_indexes'] ?? null) ? $state['tool_call_indexes'] : []);
		$context->setVar(AgentToolLoopContextKeys::MODEL_RESULTS, is_array($state['model_results'] ?? null) ? $state['model_results'] : []);
		$context->setVar(AgentToolLoopContextKeys::SELECTED_TOOL_NAMES, is_array($state['selected_tool_names'] ?? null) ? $state['selected_tool_names'] : []);
		$context->setVar(AgentToolLoopContextKeys::CAPABILITY_SELECTION_APPLIED, (bool)($state['capability_selection_applied'] ?? false));
		$context->setVar(AgentToolLoopContextKeys::SUSPENSION, $suspension);
		$context->setVar(AgentToolLoopContextKeys::INTERACTION_REQUESTS, $suspension->getRequests());
		$context->setVar(AgentToolLoopContextKeys::EXECUTION_STATUS, AgentExecutionStatus::RUNNING);
		$context->setVar(AgentToolLoopContextKeys::SUSPENDED, false);
		$context->setVar(AgentToolLoopContextKeys::COMPLETED, false);
		$context->setVar(AgentToolLoopContextKeys::FAILURE_CODE, '');
		$context->setVar(AgentToolLoopContextKeys::FAILURE_MESSAGE, '');
		$context->setVar(AgentToolLoopContextKeys::FAILURE_DETAIL, []);
	}

	private function apply(IAgentContext $context, AgentStageResult $result): void {
		foreach($result->getPatch() as $key => $value) {
			$context->setVar($key, $value);
		}
	}

	private function hasPendingCalls(IAgentContext $context): bool {
		$calls = $context->getVar(AgentToolLoopContextKeys::PENDING_TOOL_CALLS);
		return is_array($calls) && $calls !== [];
	}

	private function hasFailure(IAgentContext $context): bool {
		return trim((string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? '')) !== '';
	}

	/** @return array<string,mixed> */
	private function buildInteractionResponse(IAgentContext $context): array {
		$requests = $context->getVar(AgentToolLoopContextKeys::INTERACTION_REQUESTS);
		$result = [];

		foreach(is_array($requests) ? $requests : [] as $request) {
			if($request instanceof AgentInteractionRequest) {
				$result[] = $request->toArray();
			}
		}

		return [
			'ok' => false,
			'status' => 'confirmation_required',
			'requires_confirmation' => true,
			'resume_handle' => (string)($context->getVar(AgentToolLoopContextKeys::RESUME_HANDLE) ?? ''),
			'interaction_requests' => $result
		];
	}

	/** @return array<string,mixed> */
	private function buildExecutionResponse(IAgentContext $context): array {
		$toolResults = $context->getVar(AgentToolLoopContextKeys::TOOL_RESULTS);
		$toolResults = is_array($toolResults) ? $toolResults : [];
		$last = $toolResults !== [] ? end($toolResults) : null;

		if($last instanceof AgentToolResult) {
			return [
				'ok' => $last->isSuccess(),
				'status' => $last->isSuccess() ? 'executed' : 'blocked_or_failed',
				'tool_result' => $last->toArray(),
				'contract_validations' => $this->normalizeObjects($context->getVar(AgentToolLoopContextKeys::TOOL_CONTRACT_VALIDATIONS)),
				'budget_assessments' => $this->normalizeObjects($context->getVar(AgentToolLoopContextKeys::BUDGET_ASSESSMENTS)),
				'result_verifications' => $this->normalizeObjects($context->getVar(AgentToolLoopContextKeys::RESULT_VERIFICATIONS))
			];
		}

		return [
			'ok' => true,
			'status' => 'declined_or_no_execution',
			'tool_results' => $this->normalizeObjects($toolResults)
		];
	}

	/** @return array<string,mixed> */
	private function buildFailureResponse(IAgentContext $context): array {
		return [
			'ok' => false,
			'status' => 'failed',
			'error_code' => (string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? ''),
			'error' => (string)($context->getVar(AgentToolLoopContextKeys::FAILURE_MESSAGE) ?? ''),
			'detail' => $context->getVar(AgentToolLoopContextKeys::FAILURE_DETAIL)
		];
	}

	/** @return array<string,mixed> */
	private function failure(string $message, string $exception = ''): array {
		$result = [
			'ok' => false,
			'status' => 'failed',
			'error' => $message
		];

		if($exception !== '') {
			$result['exception'] = $exception;
		}

		return $result;
	}

	/** @param array<int,array<string,mixed>> $definitions */
	private function hasFunction(array $definitions, string $functionName): bool {
		foreach($definitions as $definition) {
			if(trim((string)($definition['function']['name'] ?? '')) === $functionName) {
				return true;
			}
		}

		return false;
	}

	/** @return array<int,array<string,mixed>> */
	private function normalizeDefinitions(mixed $definitions): array {
		$result = [];

		foreach(is_array($definitions) ? $definitions : [] as $definition) {
			if(is_array($definition)) {
				$result[] = $definition;
			}
		}

		return $result;
	}

	/** @return array<int,AiToolCall> */
	private function restoreToolCalls(mixed $values): array {
		$result = [];
		foreach(is_array($values) ? $values : [] as $value) {
			if(is_array($value)) {
				$result[] = AiToolCall::fromArray($value);
			}
		}
		return $result;
	}

	/** @return array<int,AgentAction> */
	private function restoreActions(mixed $values): array {
		$result = [];
		foreach(is_array($values) ? $values : [] as $value) {
			if(is_array($value)) {
				$result[] = AgentAction::fromArray($value);
			}
		}
		return $result;
	}

	/** @return array<int,AgentActionDecision> */
	private function restoreActionDecisions(mixed $values): array {
		$result = [];
		foreach(is_array($values) ? $values : [] as $value) {
			if(is_array($value)) {
				$result[] = AgentActionDecision::fromArray($value);
			}
		}
		return $result;
	}

	/** @return array<int,AgentToolResult> */
	private function restoreToolResults(mixed $values): array {
		$result = [];
		foreach(is_array($values) ? $values : [] as $value) {
			if(is_array($value)) {
				$result[] = AgentToolResult::fromArray($value);
			}
		}
		return $result;
	}

	/** @return array<int,mixed> */
	private function normalizeObjects(mixed $values): array {
		$result = [];

		foreach(is_array($values) ? $values : [] as $value) {
			if(is_object($value) && method_exists($value, 'toArray')) {
				$result[] = $value->toArray();
			}
			else {
				$result[] = $value;
			}
		}

		return $result;
	}
}
