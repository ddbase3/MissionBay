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
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentInteractionRequest;
use AssistantFoundation\Dto\AgentInteractionResponse;
use AssistantFoundation\Dto\AgentResume;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AgentSuspensionClaim;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantFoundation\Dto\AiToolCall;
use AssistantFoundation\Exception\AgentSuspensionRepositoryException;
use Base3\Event\Api\IEventManager;
use MissionBay\Event\MissionBayAgentActionAuditEvent;
use MissionBay\Dto\Assistant\PreparedAgentResume;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use MissionBay\Orchestrator\Suspension\UnavailableAgentSuspensionRepository;

/** Claims, validates, and consumes a durable one-time agent resume handle. */
final class AgentActionResumeService {

	private IAgentSuspensionRepository $suspensionRepository;

	public function __construct(
		private readonly AgentActionFingerprint $fingerprint,
		?IAgentSuspensionRepository $suspensionRepository = null,
		private readonly ?IEventManager $eventManager = null
	) {
		$this->suspensionRepository = $suspensionRepository ?? new UnavailableAgentSuspensionRepository();
	}

	public function prepare(AgentResume $resume): PreparedAgentResume {
		return new PreparedAgentResume(
			$resume,
			$this->suspensionRepository->claim($resume->getResumeHandle())
		);
	}

	public function resume(IAgentContext $context): AgentStageResult {
		$prepared = $context->getVar(AgentToolLoopContextKeys::RESUME);
		if (!$prepared instanceof PreparedAgentResume) {
			return $this->failure('invalid_agent_resume', 'Prepared agent resume state is missing.');
		}

		$resume = $prepared->getResume();
		$responses = [];
		foreach ($resume->getResponses() as $response) {
			if (isset($responses[$response->getRequestId()])) {
				return $this->releaseFailure(
					$prepared,
					'duplicate_agent_resume_response',
					'Duplicate response for interaction request: ' . $response->getRequestId()
				);
			}
			$responses[$response->getRequestId()] = $response;
		}

		$pendingCalls = $context->getVar(AgentToolLoopContextKeys::PENDING_TOOL_CALLS);
		$toolResults = $context->getVar(AgentToolLoopContextKeys::TOOL_RESULTS);
		$preapproved = $context->getVar(AgentToolLoopContextKeys::PREAPPROVED_ACTIONS);
		$pendingCalls = is_array($pendingCalls) ? $pendingCalls : [];
		$toolResults = is_array($toolResults) ? $toolResults : [];
		$preapproved = is_array($preapproved) ? $preapproved : [];
		$approvedCount = 0;
		$deniedCount = 0;
		$submittedCount = 0;
		$auditEvents = [];

		foreach ($prepared->getSuspension()->getRequests() as $request) {
			$validationError = $this->validateRequestIntegrity($request);
			if ($validationError !== null) {
				return $this->releaseFailure($prepared, 'invalid_agent_resume_snapshot', $validationError);
			}
			$response = $responses[$request->getId()] ?? null;
			if (!$response instanceof AgentInteractionResponse) {
				return $this->releaseFailure(
					$prepared,
					'missing_agent_resume_response',
					'Missing explicit response for interaction request: ' . $request->getId()
				);
			}
			unset($responses[$request->getId()]);

			if ($response->getDecision() === AgentInteractionResponse::DECISION_DENY) {
				$toolResults[] = $this->createDeclinedResult($request, $response);
				$auditEvents[] = [
					'type' => MissionBayAgentActionAuditEvent::TYPE_APPROVAL_DENIED,
					'action' => $request->getAction(),
					'reason' => trim($response->getNote()) !== '' ? $response->getNote() : 'The user declined the pending action.',
					'metadata' => ['interaction_request_id' => $request->getId()]
				];
				$deniedCount++;
				continue;
			}
			if ($response->getDecision() === AgentInteractionResponse::DECISION_APPROVE) {
				if ($request->getKind() !== AgentInteractionRequest::KIND_APPROVAL) {
					return $this->releaseFailure(
						$prepared,
						'invalid_agent_resume_decision',
						'Only approval requests accept the approve decision: ' . $request->getId()
					);
				}
				$approvedCall = $this->readToolCall($request);
				$callMetadata = $approvedCall->getMetadata();
				$callMetadata[AgentMutationCommitGuardService::TOOL_CALL_METADATA_APPROVAL_FINGERPRINT] = $request->getActionFingerprint();
				$callMetadata[AgentMutationCommitGuardService::TOOL_CALL_METADATA_INTERACTION_REQUEST] = $request->getId();
				$commitSnapshot = $request->getMetadata()[AgentMutationCommitGuardService::TOOL_CALL_METADATA_SNAPSHOT] ?? null;
				if (is_array($commitSnapshot)) {
					$callMetadata[AgentMutationCommitGuardService::TOOL_CALL_METADATA_SNAPSHOT] = $commitSnapshot;
				}
				$pendingCalls[] = new AiToolCall(
					$approvedCall->getId(),
					$approvedCall->getName(),
					$approvedCall->getArguments(),
					$callMetadata
				);
				$preapproved[$request->getAction()->getId()] = $request->getActionFingerprint();
				$auditEvents[] = [
					'type' => MissionBayAgentActionAuditEvent::TYPE_APPROVAL_GRANTED,
					'action' => $request->getAction(),
					'reason' => trim($response->getNote()) !== '' ? $response->getNote() : 'The user approved the exact pending action.',
					'metadata' => ['interaction_request_id' => $request->getId()]
				];
				$approvedCount++;
				continue;
			}
			if ($response->getDecision() === AgentInteractionResponse::DECISION_SUBMIT) {
				if (!in_array($request->getKind(), [AgentInteractionRequest::KIND_CLARIFICATION, AgentInteractionRequest::KIND_DRY_RUN], true)) {
					return $this->releaseFailure(
						$prepared,
						'invalid_agent_resume_decision',
						'The submit decision is valid only for clarification or dry-run requests: ' . $request->getId()
					);
				}
				$originalCall = $this->readToolCall($request);
				$metadata = $originalCall->getMetadata();
				$metadata['resumed_from_interaction'] = $request->getId();
				$pendingCalls[] = new AiToolCall($originalCall->getId(), $originalCall->getName(), $response->getInput(), $metadata);
				$submittedCount++;
				continue;
			}
			return $this->releaseFailure($prepared, 'invalid_agent_resume_decision', 'Unsupported resume decision.');
		}

		if ($responses !== []) {
			return $this->releaseFailure(
				$prepared,
				'unknown_agent_resume_response',
				'Resume payload contains responses for unknown interaction requests.'
			);
		}

		try {
			$this->suspensionRepository->consume($prepared->getClaim());
		} catch (AgentSuspensionRepositoryException $e) {
			$this->releaseBestEffort($prepared->getClaim());
			return $this->failure(
				'agent_resume_consume_failed',
				$e->getMessage(),
				['reason' => $e->getReason()]
			);
		} catch (\Throwable $e) {
			$this->releaseBestEffort($prepared->getClaim());
			return $this->failure(
				'agent_resume_consume_failed',
				'Agent resume handle could not be consumed.',
				['type' => get_class($e), 'message' => $e->getMessage()]
			);
		}

		foreach ($auditEvents as $auditEvent) {
			$this->emitAudit(
				$auditEvent['type'],
				$auditEvent['action'],
				$auditEvent['reason'],
				$context,
				$auditEvent['metadata']
			);
		}

		return AgentStageResult::patch([
			AgentToolLoopContextKeys::PENDING_TOOL_CALLS => $pendingCalls,
			AgentToolLoopContextKeys::TOOL_RESULTS => $toolResults,
			AgentToolLoopContextKeys::PREAPPROVED_ACTIONS => $preapproved,
			AgentToolLoopContextKeys::ACTION_REVIEW_CANDIDATES => [],
			AgentToolLoopContextKeys::INTERACTION_REQUESTS => [],
			AgentToolLoopContextKeys::SUSPENSION => null,
			AgentToolLoopContextKeys::RESUME_HANDLE => '',
			AgentToolLoopContextKeys::SUSPENDED => false,
			AgentToolLoopContextKeys::EXECUTION_STATUS => AgentExecutionStatus::RUNNING,
			AgentToolLoopContextKeys::PHASE => $pendingCalls === []
				? AgentToolLoopContextKeys::PHASE_AFTER_TOOLS
				: AgentToolLoopContextKeys::PHASE_TOOLS
		], [
			'approved' => $approvedCount,
			'denied' => $deniedCount,
			'submitted' => $submittedCount,
			'resume_handle_consumed' => true
		]);
	}

	private function validateRequestIntegrity(AgentInteractionRequest $request): ?string {
		$computed = $this->fingerprint->create($request->getAction());
		if (!hash_equals($request->getActionFingerprint(), $computed)) {
			return 'Interaction request action fingerprint does not match its action payload: ' . $request->getId();
		}
		try {
			$call = $this->readToolCall($request);
		} catch (\Throwable $exception) {
			return $exception->getMessage();
		}
		$action = $request->getAction();
		if ($call->getId() !== $action->getId() || $call->getName() !== $action->getName() || $call->getArguments() !== $action->getInput()) {
			return 'Interaction request tool call does not match the reviewed action: ' . $request->getId();
		}
		return null;
	}

	private function readToolCall(AgentInteractionRequest $request): AiToolCall {
		$toolCall = $request->getMetadata()['tool_call'] ?? null;
		if (!is_array($toolCall)) {
			throw new \RuntimeException('Interaction request does not contain a resumable tool call: ' . $request->getId());
		}
		return AiToolCall::fromArray($toolCall);
	}

	private function createDeclinedResult(AgentInteractionRequest $request, AgentInteractionResponse $response): AgentToolResult {
		$message = trim($response->getNote());
		if ($message === '') {
			$message = 'The user declined the pending action.';
		}
		return AgentToolResult::failure(
			$request->getAction()->getId(),
			$request->getAction()->getName(),
			$request->getAction()->getInput(),
			'action_declined_by_user',
			$message,
			['interaction_request_id' => $request->getId(), 'user_decision' => $response->toArray()],
			['ok' => false, 'blocked' => true, 'decision' => 'deny', 'reason' => $message, 'action' => $request->getAction()->toArray()]
		);
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
		try {
			$this->eventManager->fire(new MissionBayAgentActionAuditEvent(
				$type,
				$action,
				$reason,
				is_array($trace) ? $trace : [],
				$metadata
			));
		} catch (\Throwable) {
		}
	}

	private function releaseFailure(
		PreparedAgentResume $prepared,
		string $code,
		string $message,
		array $detail = []
	): AgentStageResult {
		$this->releaseBestEffort($prepared->getClaim());
		return $this->failure($code, $message, $detail);
	}

	private function releaseBestEffort(AgentSuspensionClaim $claim): void {
		try {
			$this->suspensionRepository->release($claim);
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
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::SUSPENDED => false
		]);
	}
}
