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

namespace MissionBay\Orchestrator\Stage;

use AssistantFoundation\Api\IAgentActionPolicy;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentActionDecision;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AgentToolContractValidation;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\Policy\IAgentActionPolicyResolver;
use MissionBay\Orchestrator\Service\AgentActionReviewService;
use MissionBay\Orchestrator\Service\AgentCapabilitySelectionGuardService;
use MissionBay\Orchestrator\Service\AgentToolContractValidationService;

/**
 * Evaluates semantic tool actions before execution. Denials become tool
 * results; approval/input decisions are converted into a suspension by the internal review service.
 */
final class AgentActionPolicyStage implements IAgentStage {

	/** @var array<int,IAgentActionPolicy>|null */
	private ?array $resolvedPolicies = null;

	private AgentActionReviewService $actionReviewService;
	private AgentToolContractValidationService $toolContractValidationService;
	private AgentCapabilitySelectionGuardService $capabilitySelectionGuardService;

	/** @param array<int,string> $policyIds */
	public function __construct(
		private readonly IAgentActionPolicyResolver $policyResolver,
		private readonly AgentActionFingerprint $fingerprint,
		private readonly string $id = 'action-policy',
		private readonly string $stageName = 'action-policy',
		private readonly array $policyIds = ['allow-all-actions'],
		?AgentActionReviewService $actionReviewService = null,
		?AgentToolContractValidationService $toolContractValidationService = null,
		?AgentCapabilitySelectionGuardService $capabilitySelectionGuardService = null
	) {
		$this->actionReviewService = $actionReviewService
			?? new AgentActionReviewService($this->fingerprint);
		$this->toolContractValidationService = $toolContractValidationService
			?? new AgentToolContractValidationService();
		$this->capabilitySelectionGuardService = $capabilitySelectionGuardService
			?? new AgentCapabilitySelectionGuardService();
	}

	public static function getName(): string { return 'agentactionpolicystage'; }
	public function id(): string { return $this->id; }
	public function name(): string { return $this->stageName; }
	public function getDescription(): string {
		return 'Evaluates semantic tool actions through configured policies before any tool implementation is executed.';
	}

	public function getAiUsage(): string {
		$usage = IAgentStage::AI_USAGE_NONE;
		foreach ($this->getPolicies() as $policy) {
			if ($policy->getAiUsage() === IAgentActionPolicy::AI_USAGE_REQUIRED) {
				return IAgentStage::AI_USAGE_REQUIRED;
			}
			if ($policy->getAiUsage() === IAgentActionPolicy::AI_USAGE_CONDITIONAL) {
				$usage = IAgentStage::AI_USAGE_CONDITIONAL;
			}
		}
		return $usage;
	}

	public function supports(IAgentContext $context): bool {
		$toolCalls = $context->getVar(AgentToolLoopContextKeys::PENDING_TOOL_CALLS);
		return $context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_TOOLS
			&& is_array($toolCalls)
			&& $toolCalls !== []
			&& $context->getVar(AgentToolLoopContextKeys::COMPLETED) !== true
			&& (string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? '') === '';
	}

	public function process(IAgentContext $context): AgentStageResult {
		$toolCalls = $context->getVar(AgentToolLoopContextKeys::PENDING_TOOL_CALLS);
		$existingActions = $context->getVar(AgentToolLoopContextKeys::ACTIONS);
		$existingDecisions = $context->getVar(AgentToolLoopContextKeys::ACTION_DECISIONS);
		$existingResults = $context->getVar(AgentToolLoopContextKeys::TOOL_RESULTS);
		$existingContractValidations = $context->getVar(AgentToolLoopContextKeys::TOOL_CONTRACT_VALIDATIONS);
		$tools = $context->getVar(AgentToolLoopContextKeys::TOOLS);
		$preapproved = $context->getVar(AgentToolLoopContextKeys::PREAPPROVED_ACTIONS);
		$iteration = (int)($context->getVar(AgentToolLoopContextKeys::ITERATION) ?? 0);

		$toolCalls = is_array($toolCalls) ? $toolCalls : [];
		$existingActions = is_array($existingActions) ? $existingActions : [];
		$existingDecisions = is_array($existingDecisions) ? $existingDecisions : [];
		$existingResults = is_array($existingResults) ? $existingResults : [];
		$existingContractValidations = is_array($existingContractValidations) ? $existingContractValidations : [];
		$tools = is_array($tools) ? $tools : [];
		$preapproved = is_array($preapproved) ? $preapproved : [];

		$allowedCalls = [];
		$actions = $existingActions;
		$knownActionIndexes = [];
		foreach ($actions as $actionIndex => $knownAction) {
			if ($knownAction instanceof AgentAction) {
				$knownActionIndexes[$knownAction->getId()] = $actionIndex;
			}
		}
		$decisions = $existingDecisions;
		$toolResults = $existingResults;
		$contractValidations = $existingContractValidations;
		$reviewCandidates = [];

		foreach ($toolCalls as $call) {
			if (!$call instanceof AiToolCall) {
				return $this->failure('invalid_tool_call', 'Action policy stage received a non-normalized tool call.', ['type' => get_debug_type($call)]);
			}

			$action = $this->createAction($call, $iteration);
			$effectiveCall = $this->normalizeCallIdentity($call, $action);
			$actionIndex = $knownActionIndexes[$action->getId()] ?? null;
			if ($actionIndex === null) {
				$actions[] = $action;
				$knownActionIndexes[$action->getId()] = array_key_last($actions);
			} elseif ($actions[$actionIndex] instanceof AgentAction) {
				$knownFingerprint = $this->fingerprint->create($actions[$actionIndex]);
				if (!hash_equals($knownFingerprint, $this->fingerprint->create($action))) {
					$actions[$actionIndex] = $action;
				}
			}

			if (!$this->capabilitySelectionGuardService->isAllowed($context, $effectiveCall->getName())) {
				$toolResults[] = $this->capabilitySelectionGuardService->createFailure($context, $effectiveCall, $iteration);
				continue;
			}

			$contractValidation = $this->toolContractValidationService->validateInput($effectiveCall, $tools);
			$contractValidations[] = $contractValidation;

			if (!$contractValidation->passes()) {
				$toolResults[] = $this->createContractFailureResult($action, $contractValidation, $iteration);
				continue;
			}

			$evaluation = $this->evaluateAction($action, $context, $preapproved);
			foreach ($evaluation['decisions'] as $policyDecision) {
				$decisions[] = $policyDecision;
			}
			$decision = $evaluation['final'];

			if ($decision->isAllowed()) {
				$allowedCalls[] = $effectiveCall;
				continue;
			}
			if ($decision->getDecision() === AgentActionDecision::DECISION_DENY) {
				$toolResults[] = $this->createBlockedResult($action, $decision, $iteration);
				continue;
			}
			$reviewCandidates[] = [
				'action' => $action,
				'decision' => $decision,
				'tool_call' => $effectiveCall
			];
		}

		$patch = [
			AgentToolLoopContextKeys::ACTIONS => $actions,
			AgentToolLoopContextKeys::ACTION_DECISIONS => $decisions,
			AgentToolLoopContextKeys::ACTION_REVIEW_CANDIDATES => $reviewCandidates,
			AgentToolLoopContextKeys::PREAPPROVED_ACTIONS => [],
			AgentToolLoopContextKeys::PENDING_TOOL_CALLS => $allowedCalls,
			AgentToolLoopContextKeys::TOOL_RESULTS => $toolResults,
			AgentToolLoopContextKeys::TOOL_CONTRACT_VALIDATIONS => $contractValidations,
			AgentToolLoopContextKeys::PHASE => $allowedCalls === [] && $reviewCandidates === []
				? AgentToolLoopContextKeys::PHASE_AFTER_TOOLS
				: AgentToolLoopContextKeys::PHASE_TOOLS
		];

		if ($reviewCandidates !== []) {
			return $this->actionReviewService->review($context, $patch);
		}

		return AgentStageResult::patch($patch);
	}

	private function createAction(AiToolCall $call, int $iteration): AgentAction {
		$actionId = trim($call->getId());
		if ($actionId === '') {
			$actionId = uniqid('action_', true);
		}
		return new AgentAction(
			$actionId,
			AgentAction::TYPE_TOOL_CALL,
			trim($call->getName()),
			$call->getArguments(),
			['iteration' => $iteration, 'tool_call' => $call->getMetadata()]
		);
	}

	private function normalizeCallIdentity(AiToolCall $call, AgentAction $action): AiToolCall {
		if (trim($call->getId()) !== '') {
			return $call;
		}
		$metadata = $call->getMetadata();
		$metadata['generated_call_id'] = true;
		return new AiToolCall($action->getId(), $call->getName(), $call->getArguments(), $metadata);
	}

	/** @param array<string,string> $preapproved @return array{final:AgentActionDecision,decisions:array<int,AgentActionDecision>} */
	private function evaluateAction(AgentAction $action, IAgentContext $context, array $preapproved): array {
		$lastAllow = AgentActionDecision::allow($action->getId(), 'No blocking action policy decision.');
		$decisions = [];
		foreach ($this->getPolicies() as $policy) {
			try {
				$decision = $policy->evaluate($action, $context);
			} catch (\Throwable $e) {
				throw new \RuntimeException('Agent action policy failed (' . $policy->id() . '): ' . $e->getMessage(), 0, $e);
			}
			if ($decision->getActionId() !== $action->getId()) {
				throw new \RuntimeException('Agent action policy returned a decision for a different action: ' . $policy->id());
			}
			$decision = new AgentActionDecision(
				$decision->getActionId(),
				$decision->getDecision(),
				$decision->getReason(),
				array_merge([
					'policy_id' => $policy->id(),
					'policy_name' => $policy->name(),
					'policy_implementation' => $policy::getName()
				], $decision->getMetadata())
			);

			if (
				$decision->getDecision() === AgentActionDecision::DECISION_REQUIRE_APPROVAL
				&& isset($preapproved[$action->getId()])
				&& hash_equals((string)$preapproved[$action->getId()], $this->fingerprint->create($action))
			) {
				$decision = AgentActionDecision::allow(
					$action->getId(),
					'Exact action payload was explicitly approved by the user.',
					array_merge($decision->getMetadata(), [
						'preapproved' => true,
						'approval_fingerprint' => $preapproved[$action->getId()]
					])
				);
			}

			$decisions[] = $decision;
			if (!$decision->isAllowed()) {
				return ['final' => $decision, 'decisions' => $decisions];
			}
			$lastAllow = $decision;
		}
		return ['final' => $lastAllow, 'decisions' => $decisions];
	}

	private function createBlockedResult(AgentAction $action, AgentActionDecision $decision, int $iteration): AgentToolResult {
		$errorCode = match ($decision->getDecision()) {
			AgentActionDecision::DECISION_DENY => 'action_denied',
			AgentActionDecision::DECISION_REQUIRE_APPROVAL => 'action_requires_approval',
			AgentActionDecision::DECISION_REQUIRE_DRY_RUN => 'action_requires_dry_run',
			AgentActionDecision::DECISION_REQUIRE_CLARIFICATION => 'action_requires_clarification',
			default => 'action_blocked'
		};
		$message = trim($decision->getReason());
		if ($message === '') {
			$message = 'The proposed action was blocked by an action policy.';
		}
		$output = [
			'ok' => false,
			'blocked' => true,
			'decision' => $decision->getDecision(),
			'reason' => $message,
			'action' => $action->toArray()
		];
		return AgentToolResult::failure(
			$action->getId(),
			$action->getName(),
			$action->getInput(),
			$errorCode,
			$message,
			['iteration' => $iteration, 'action' => $action->toArray(), 'action_decision' => $decision->toArray()],
			$output
		);
	}

	private function createContractFailureResult(
		AgentAction $action,
		AgentToolContractValidation $validation,
		int $iteration
	): AgentToolResult {
		$message = trim($validation->getSummary());
		if ($message === '') {
			$message = 'Tool arguments failed contract validation.';
		}

		return AgentToolResult::failure(
			$action->getId(),
			$action->getName(),
			$action->getInput(),
			$validation->getReasonCode(),
			$message,
			[
				'iteration' => $iteration,
				'action' => $action->toArray(),
				'contract_validation' => $validation->toArray(),
				'blocked_before_policy' => true,
				'blocked_before_execution' => true
			],
			[
				'ok' => false,
				'blocked' => true,
				'error_code' => $validation->getReasonCode(),
				'error' => $message,
				'issues' => $validation->getIssues()
			]
		);
	}

	/** @return array<int,IAgentActionPolicy> */
	private function getPolicies(): array {
		if ($this->resolvedPolicies === null) {
			$this->resolvedPolicies = $this->policyResolver->resolve($this->policyIds);
		}
		return $this->resolvedPolicies;
	}

	/** @param array<string,mixed> $detail */
	private function failure(string $code, string $message, array $detail): AgentStageResult {
		return AgentStageResult::patch([
			AgentToolLoopContextKeys::FAILURE_CODE => $code,
			AgentToolLoopContextKeys::FAILURE_MESSAGE => $message,
			AgentToolLoopContextKeys::FAILURE_DETAIL => $detail,
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FAILED
		]);
	}
}
