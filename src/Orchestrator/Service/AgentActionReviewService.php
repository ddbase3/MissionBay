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

namespace MissionBay\Orchestrator\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentSuspensionRepository;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentActionDecision;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentInteractionRequest;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AgentSuspension;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantFoundation\Dto\AiToolCall;
use AssistantFoundation\Exception\AgentSuspensionRepositoryException;
use Base3\Event\Api\IEventManager;
use MissionBay\Event\MissionBayAgentActionAuditEvent;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use MissionBay\Orchestrator\Suspension\UnavailableAgentSuspensionRepository;

/** Converts policy review decisions into a durable, transport-neutral suspension. */
final class AgentActionReviewService {

	private IAgentSuspensionRepository $suspensionRepository;
	private AgentMutationCommitGuardService $mutationCommitGuardService;

	public function __construct(
		private readonly AgentActionFingerprint $fingerprint,
		?IAgentSuspensionRepository $suspensionRepository = null,
		private readonly int $suspensionTtlSeconds = 900,
		?AgentMutationCommitGuardService $mutationCommitGuardService = null,
		private readonly ?IEventManager $eventManager = null
	) {
		$this->suspensionRepository = $suspensionRepository ?? new UnavailableAgentSuspensionRepository();
		$this->mutationCommitGuardService = $mutationCommitGuardService
			?? new AgentMutationCommitGuardService($this->fingerprint, $this->eventManager);
		if ($suspensionTtlSeconds < 1) {
			throw new \InvalidArgumentException('Agent suspension TTL must be greater than zero.');
		}
	}

	public function review(IAgentContext $context, array $projectedPatch = []): AgentStageResult {
		$candidates = $projectedPatch[AgentToolLoopContextKeys::ACTION_REVIEW_CANDIDATES]
			?? $context->getVar(AgentToolLoopContextKeys::ACTION_REVIEW_CANDIDATES);
		if (!is_array($candidates) || $candidates === []) {
			return AgentStageResult::none();
		}

		$requests = [];
		$suspensionRequests = [];
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

			$publicMetadata = [
				'decision' => $decision->toArray(),
				'tool_call' => $toolCall->toArray()
			];
			$suspensionMetadata = $publicMetadata;

			if ($kind === AgentInteractionRequest::KIND_APPROVAL) {
				try {
					$commitSnapshot = $this->mutationCommitGuardService->capture($action, $toolCall, $context);
				} catch (\Throwable $e) {
					return $this->failure(
						'mutation_commit_snapshot_failed',
						$e->getMessage(),
						['type' => get_class($e), 'action_id' => $action->getId()]
					);
				}
				if ($commitSnapshot !== null) {
					$suspensionMetadata[AgentMutationCommitGuardService::TOOL_CALL_METADATA_SNAPSHOT] = $commitSnapshot->toArray();
				}
			}

			$requestArguments = [
				'id' => $requestId,
				'kind' => $kind,
				'action' => $action,
				'actionFingerprint' => $actionFingerprint,
				'title' => $title !== '' ? $title : 'Review tool action',
				'message' => $message !== '' ? $message : 'Explicit user input is required before this action may continue.',
				'summary' => $summary,
				'risk' => $risk !== '' ? $risk : 'medium'
			];
			$requests[] = new AgentInteractionRequest(...$requestArguments, metadata: $publicMetadata);
			$suspensionRequests[] = new AgentInteractionRequest(...$requestArguments, metadata: $suspensionMetadata);
		}

		$status = $hasInputRequest
			? AgentExecutionStatus::AWAITING_INPUT
			: AgentExecutionStatus::AWAITING_APPROVAL;
		$suspension = new AgentSuspension(
			id: $this->createSuspensionId(),
			status: $status,
			requests: $suspensionRequests,
			state: $this->createSnapshot($context, $projectedPatch),
			createdAt: gmdate('c'),
			metadata: [
				'node_id' => (string)($context->getVar(AgentToolLoopContextKeys::NODE_ID) ?? ''),
				'iteration' => (int)($context->getVar(AgentToolLoopContextKeys::ITERATION) ?? 0)
			]
		);

		try {
			$resumeHandle = $this->suspensionRepository->create($suspension, $this->suspensionTtlSeconds);
		} catch (AgentSuspensionRepositoryException $e) {
			return $this->failure(
				'suspension_persistence_failed',
				$e->getMessage(),
				['reason' => $e->getReason()]
			);
		} catch (\Throwable $e) {
			return $this->failure(
				'suspension_persistence_failed',
				'Agent suspension could not be persisted.',
				['type' => get_class($e), 'message' => $e->getMessage()]
			);
		}

		foreach ($requests as $request) {
			if ($request->getKind() !== AgentInteractionRequest::KIND_APPROVAL) {
				continue;
			}
			$this->emitAudit(
				MissionBayAgentActionAuditEvent::TYPE_APPROVAL_REQUESTED,
				$request->getAction(),
				$request->getMessage(),
				$context,
				[
					'interaction_request_id' => $request->getId(),
					'suspension_id' => $suspension->getId(),
					'risk' => $request->getRisk()
				]
			);
		}

		return AgentStageResult::patch(array_merge($projectedPatch, [
			AgentToolLoopContextKeys::INTERACTION_REQUESTS => $requests,
			AgentToolLoopContextKeys::SUSPENSION => null,
			AgentToolLoopContextKeys::RESUME_HANDLE => $resumeHandle,
			AgentToolLoopContextKeys::EXECUTION_STATUS => $status,
			AgentToolLoopContextKeys::SUSPENDED => true,
			AgentToolLoopContextKeys::PHASE => $status === AgentExecutionStatus::AWAITING_INPUT
				? AgentToolLoopContextKeys::PHASE_AWAITING_INPUT
				: AgentToolLoopContextKeys::PHASE_AWAITING_APPROVAL,
			AgentToolLoopContextKeys::FINAL_RESPONSE_MODE => AgentToolLoopContextKeys::FINAL_RESPONSE_NONE
		]), [
			'suspension_id' => $suspension->getId(),
			'status' => $status,
			'request_count' => count($requests),
			'ttl_seconds' => $this->suspensionTtlSeconds
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
	private function createSnapshot(IAgentContext $context, array $projectedPatch): array {
		return [
			'iteration' => (int)($context->getVar(AgentToolLoopContextKeys::ITERATION) ?? 0),
			'call_index' => (int)($context->getVar(AgentToolLoopContextKeys::CALL_INDEX) ?? 0),
			'messages' => $this->arrayValue($context, AgentToolLoopContextKeys::MESSAGES, $projectedPatch),
			'pending_tool_calls' => $this->mapObjects($this->arrayValue($context, AgentToolLoopContextKeys::PENDING_TOOL_CALLS, $projectedPatch), AiToolCall::class),
			'actions' => $this->mapObjects($this->arrayValue($context, AgentToolLoopContextKeys::ACTIONS, $projectedPatch), AgentAction::class),
			'action_decisions' => $this->mapObjects($this->arrayValue($context, AgentToolLoopContextKeys::ACTION_DECISIONS, $projectedPatch), AgentActionDecision::class),
			'tool_results' => $this->mapObjects($this->arrayValue($context, AgentToolLoopContextKeys::TOOL_RESULTS, $projectedPatch), AgentToolResult::class),
			'observations' => $this->mapObjects($this->arrayValue($context, AgentToolLoopContextKeys::OBSERVATIONS, $projectedPatch), AgentToolResult::class),
			'executed_tool_calls' => $this->arrayValue($context, AgentToolLoopContextKeys::EXECUTED_TOOL_CALLS, $projectedPatch),
			'tool_call_indexes' => $this->arrayValue($context, AgentToolLoopContextKeys::TOOL_CALL_INDEXES, $projectedPatch),
			'model_results' => $this->arrayValue($context, AgentToolLoopContextKeys::MODEL_RESULTS, $projectedPatch),
			'model_decision_assessments' => $this->arrayValue($context, AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS, $projectedPatch),
			'selected_tool_names' => $this->arrayValue($context, AgentToolLoopContextKeys::SELECTED_TOOL_NAMES, $projectedPatch),
			'capability_selection_applied' => $this->boolValue($context, AgentToolLoopContextKeys::CAPABILITY_SELECTION_APPLIED, $projectedPatch)
		];
	}

	/** @return array<int,mixed> */
	private function arrayValue(IAgentContext $context, string $key, array $projectedPatch = []): array {
		$value = array_key_exists($key, $projectedPatch)
			? $projectedPatch[$key]
			: $context->getVar($key);
		return is_array($value) ? $value : [];
	}

	private function boolValue(IAgentContext $context, string $key, array $projectedPatch = []): bool {
		$value = array_key_exists($key, $projectedPatch)
			? $projectedPatch[$key]
			: $context->getVar($key);
		return $value === true;
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

	/** @param array<string,mixed> $metadata */
	private function emitAudit(
		string $type,
		AgentAction $action,
		string $reason,
		IAgentContext $context,
		array $metadata = []
	): void {
		if (!$this->eventManager instanceof IEventManager) {
			return;
		}
		$trace = $context->getVar(AgentToolLoopContextKeys::TRACE);
		$trace = is_array($trace) ? $trace : [];
		$trace['source'] = 'agent';
		$nodeId = trim((string)($context->getVar(AgentToolLoopContextKeys::NODE_ID) ?? ''));
		if ($nodeId !== '') {
			$trace['node_id'] = $nodeId;
		}

		try {
			$this->eventManager->fire(new MissionBayAgentActionAuditEvent(
				$type,
				$action,
				$reason,
				$trace,
				$metadata
			));
		} catch (\Throwable) {
		}
	}

	/** @param array<string,mixed> $detail */
	private function failure(string $code, string $message, array $detail = []): AgentStageResult {
		return AgentStageResult::patch([
			AgentToolLoopContextKeys::FAILURE_CODE => $code,
			AgentToolLoopContextKeys::FAILURE_MESSAGE => $message,
			AgentToolLoopContextKeys::FAILURE_DETAIL => $detail,
			AgentToolLoopContextKeys::EXECUTION_STATUS => AgentExecutionStatus::FAILED,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FAILED,
			AgentToolLoopContextKeys::COMPLETED => false
		]);
	}
}
