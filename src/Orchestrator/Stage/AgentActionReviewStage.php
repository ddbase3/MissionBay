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

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentActionDecision;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentInteractionRequest;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AgentSuspension;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Orchestrator\AgentActionFingerprint;

/** Converts policy review decisions into a transport-neutral suspension. */
final class AgentActionReviewStage implements IAgentStage {

	public function __construct(
		private readonly AgentActionFingerprint $fingerprint,
		private readonly string $id = 'action-review',
		private readonly string $stageName = 'action-review'
	) {}

	public static function getName(): string { return 'agentactionreviewstage'; }
	public function id(): string { return $this->id; }
	public function name(): string { return $this->stageName; }
	public function getDescription(): string {
		return 'Suspends the run and emits structured approval, clarification, or dry-run requests before tool execution.';
	}
	public function getAiUsage(): string { return IAgentStage::AI_USAGE_NONE; }

	public function supports(IAgentContext $context): bool {
		$candidates = $context->getVar(AgentToolLoopContextKeys::ACTION_REVIEW_CANDIDATES);
		return $context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_TOOLS
			&& is_array($candidates)
			&& $candidates !== []
			&& $context->getVar(AgentToolLoopContextKeys::SUSPENDED) !== true
			&& (string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? '') === '';
	}

	public function process(IAgentContext $context): AgentStageResult {
		$candidates = $context->getVar(AgentToolLoopContextKeys::ACTION_REVIEW_CANDIDATES);
		if (!is_array($candidates) || $candidates === []) {
			return AgentStageResult::none();
		}

		$requests = [];
		$hasInputRequest = false;
		foreach ($candidates as $candidate) {
			if (!is_array($candidate)) {
				return $this->failure('invalid_action_review_candidate', 'Action review candidate must be an array.');
			}
			$action = $candidate['action'] ?? null;
			$decision = $candidate['decision'] ?? null;
			$toolCall = $candidate['tool_call'] ?? null;
			if (!$action instanceof AgentAction || !$decision instanceof AgentActionDecision || !$toolCall instanceof AiToolCall) {
				return $this->failure('invalid_action_review_candidate', 'Action review candidate contains invalid runtime objects.');
			}

			$kind = $this->resolveKind($decision);
			if ($kind !== AgentInteractionRequest::KIND_APPROVAL) {
				$hasInputRequest = true;
			}
			$interaction = $decision->getMetadata()['interaction'] ?? [];
			if (!is_array($interaction)) {
				$interaction = [];
			}
			$actionFingerprint = $this->fingerprint->create($action);
			$requestId = 'air-' . substr($actionFingerprint, 0, 16) . '-' . $action->getId();
			$title = trim((string)($interaction['title'] ?? 'Review tool action'));
			$message = trim((string)($interaction['message'] ?? $decision->getReason()));
			$summary = is_array($interaction['summary'] ?? null)
				? $interaction['summary']
				: ['tool' => $action->getName(), 'input' => $action->getInput()];
			$risk = trim((string)($interaction['risk'] ?? 'medium'));

			$requests[] = new AgentInteractionRequest(
				id: $requestId,
				kind: $kind,
				action: $action,
				actionFingerprint: $actionFingerprint,
				title: $title !== '' ? $title : 'Review tool action',
				message: $message !== '' ? $message : 'Explicit user input is required before this action may continue.',
				summary: $summary,
				risk: $risk !== '' ? $risk : 'medium',
				metadata: [
					'decision' => $decision->toArray(),
					'tool_call' => $toolCall->toArray()
				]
			);
		}

		$status = $hasInputRequest
			? AgentExecutionStatus::AWAITING_INPUT
			: AgentExecutionStatus::AWAITING_APPROVAL;
		$suspension = new AgentSuspension(
			id: $this->createSuspensionId(),
			status: $status,
			requests: $requests,
			state: $this->createSnapshot($context),
			createdAt: gmdate('c'),
			metadata: [
				'node_id' => (string)($context->getVar(AgentToolLoopContextKeys::NODE_ID) ?? ''),
				'iteration' => (int)($context->getVar(AgentToolLoopContextKeys::ITERATION) ?? 0)
			]
		);

		return AgentStageResult::patch([
			AgentToolLoopContextKeys::INTERACTION_REQUESTS => $requests,
			AgentToolLoopContextKeys::SUSPENSION => $suspension,
			AgentToolLoopContextKeys::EXECUTION_STATUS => $status,
			AgentToolLoopContextKeys::SUSPENDED => true,
			AgentToolLoopContextKeys::PHASE => $status === AgentExecutionStatus::AWAITING_INPUT
				? AgentToolLoopContextKeys::PHASE_AWAITING_INPUT
				: AgentToolLoopContextKeys::PHASE_AWAITING_APPROVAL,
			AgentToolLoopContextKeys::FINAL_RESPONSE_MODE => AgentToolLoopContextKeys::FINAL_RESPONSE_NONE
		], [
			'suspension_id' => $suspension->getId(),
			'status' => $status,
			'request_count' => count($requests)
		]);
	}

	private function resolveKind(AgentActionDecision $decision): string {
		return match($decision->getDecision()) {
			AgentActionDecision::DECISION_REQUIRE_APPROVAL => AgentInteractionRequest::KIND_APPROVAL,
			AgentActionDecision::DECISION_REQUIRE_CLARIFICATION => AgentInteractionRequest::KIND_CLARIFICATION,
			AgentActionDecision::DECISION_REQUIRE_DRY_RUN => AgentInteractionRequest::KIND_DRY_RUN,
			default => throw new \RuntimeException('Unsupported action review decision: ' . $decision->getDecision())
		};
	}

	/** @return array<string,mixed> */
	private function createSnapshot(IAgentContext $context): array {
		return [
			'iteration' => (int)($context->getVar(AgentToolLoopContextKeys::ITERATION) ?? 0),
			'call_index' => (int)($context->getVar(AgentToolLoopContextKeys::CALL_INDEX) ?? 0),
			'messages' => $this->arrayValue($context, AgentToolLoopContextKeys::MESSAGES),
			'pending_tool_calls' => $this->mapObjects($this->arrayValue($context, AgentToolLoopContextKeys::PENDING_TOOL_CALLS), AiToolCall::class),
			'actions' => $this->mapObjects($this->arrayValue($context, AgentToolLoopContextKeys::ACTIONS), AgentAction::class),
			'action_decisions' => $this->mapObjects($this->arrayValue($context, AgentToolLoopContextKeys::ACTION_DECISIONS), AgentActionDecision::class),
			'tool_results' => $this->mapObjects($this->arrayValue($context, AgentToolLoopContextKeys::TOOL_RESULTS), AgentToolResult::class),
			'observations' => $this->mapObjects($this->arrayValue($context, AgentToolLoopContextKeys::OBSERVATIONS), AgentToolResult::class),
			'executed_tool_calls' => $this->arrayValue($context, AgentToolLoopContextKeys::EXECUTED_TOOL_CALLS),
			'tool_call_indexes' => $this->arrayValue($context, AgentToolLoopContextKeys::TOOL_CALL_INDEXES),
			'model_results' => $this->arrayValue($context, AgentToolLoopContextKeys::MODEL_RESULTS)
		];
	}

	/** @return array<int,mixed> */
	private function arrayValue(IAgentContext $context, string $key): array {
		$value = $context->getVar($key);
		return is_array($value) ? $value : [];
	}

	/** @param array<int,mixed> $values @return array<int,array<string,mixed>> */
	private function mapObjects(array $values, string $class): array {
		$result = [];
		foreach ($values as $value) {
			if ($value instanceof $class && method_exists($value, 'toArray')) {
				$result[] = $value->toArray();
			}
		}
		return $result;
	}

	private function createSuspensionId(): string {
		try {
			return 'agent-susp-' . bin2hex(random_bytes(16));
		} catch (\Throwable) {
			return 'agent-susp-' . sha1(uniqid('', true));
		}
	}

	private function failure(string $code, string $message): AgentStageResult {
		return AgentStageResult::patch([
			AgentToolLoopContextKeys::FAILURE_CODE => $code,
			AgentToolLoopContextKeys::FAILURE_MESSAGE => $message,
			AgentToolLoopContextKeys::FAILURE_DETAIL => [],
			AgentToolLoopContextKeys::EXECUTION_STATUS => AgentExecutionStatus::FAILED,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FAILED,
			AgentToolLoopContextKeys::COMPLETED => false
		]);
	}
}
