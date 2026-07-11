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
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentInteractionRequest;
use AssistantFoundation\Dto\AgentInteractionResponse;
use MissionBay\Dto\Assistant\PreparedAgentResume;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Orchestrator\AgentActionFingerprint;

/** Applies explicit responses to a suspended action batch. */
final class AgentActionResumeStage implements IAgentStage {

	public function __construct(
		private readonly AgentActionFingerprint $fingerprint,
		private readonly string $id = 'action-resume',
		private readonly string $stageName = 'action-resume'
	) {}

	public static function getName(): string { return 'agentactionresumestage'; }
	public function id(): string { return $this->id; }
	public function name(): string { return $this->stageName; }
	public function getDescription(): string {
		return 'Validates explicit responses to a suspended action batch and resumes only approved or revised tool calls.';
	}
	public function getAiUsage(): string { return IAgentStage::AI_USAGE_NONE; }

	public function supports(IAgentContext $context): bool {
		return $context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_RESUME
			&& $context->getVar(AgentToolLoopContextKeys::RESUME) instanceof PreparedAgentResume
			&& (string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? '') === '';
	}

	public function process(IAgentContext $context): AgentStageResult {
		$prepared = $context->getVar(AgentToolLoopContextKeys::RESUME);
		if (!$prepared instanceof PreparedAgentResume) {
			return $this->failure('invalid_agent_resume', 'Agent resume payload is missing.');
		}
		$resume = $prepared->getResume();
		$responses = [];
		foreach ($resume->getResponses() as $response) {
			if (isset($responses[$response->getRequestId()])) {
				return $this->failure('duplicate_agent_resume_response', 'Duplicate response for interaction request: ' . $response->getRequestId());
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

		foreach ($prepared->getSuspension()->getRequests() as $request) {
			$validationError = $this->validateRequestIntegrity($request);
			if ($validationError !== null) {
				return $this->failure('invalid_agent_resume_snapshot', $validationError);
			}
			$response = $responses[$request->getId()] ?? null;
			if (!$response instanceof AgentInteractionResponse) {
				return $this->failure('missing_agent_resume_response', 'Missing explicit response for interaction request: ' . $request->getId());
			}
			unset($responses[$request->getId()]);

			if ($response->getDecision() === AgentInteractionResponse::DECISION_DENY) {
				$toolResults[] = $this->createDeclinedResult($request, $response);
				$deniedCount++;
				continue;
			}
			if ($response->getDecision() === AgentInteractionResponse::DECISION_APPROVE) {
				if ($request->getKind() !== AgentInteractionRequest::KIND_APPROVAL) {
					return $this->failure('invalid_agent_resume_decision', 'Only approval requests accept the approve decision: ' . $request->getId());
				}
				$pendingCalls[] = $this->readToolCall($request);
				$preapproved[$request->getAction()->getId()] = $request->getActionFingerprint();
				$approvedCount++;
				continue;
			}
			if ($response->getDecision() === AgentInteractionResponse::DECISION_SUBMIT) {
				if (!in_array($request->getKind(), [AgentInteractionRequest::KIND_CLARIFICATION, AgentInteractionRequest::KIND_DRY_RUN], true)) {
					return $this->failure('invalid_agent_resume_decision', 'The submit decision is valid only for clarification or dry-run requests: ' . $request->getId());
				}
				$originalCall = $this->readToolCall($request);
				$metadata = $originalCall->getMetadata();
				$metadata['resumed_from_interaction'] = $request->getId();
				$pendingCalls[] = new AiToolCall($originalCall->getId(), $originalCall->getName(), $response->getInput(), $metadata);
				$submittedCount++;
				continue;
			}
			return $this->failure('invalid_agent_resume_decision', 'Unsupported resume decision.');
		}

		if ($responses !== []) {
			return $this->failure('unknown_agent_resume_response', 'Resume payload contains responses for unknown interaction requests.');
		}

		return AgentStageResult::patch([
			AgentToolLoopContextKeys::PENDING_TOOL_CALLS => $pendingCalls,
			AgentToolLoopContextKeys::TOOL_RESULTS => $toolResults,
			AgentToolLoopContextKeys::PREAPPROVED_ACTIONS => $preapproved,
			AgentToolLoopContextKeys::ACTION_REVIEW_CANDIDATES => [],
			AgentToolLoopContextKeys::INTERACTION_REQUESTS => [],
			AgentToolLoopContextKeys::SUSPENSION => null,
			AgentToolLoopContextKeys::SUSPENDED => false,
			AgentToolLoopContextKeys::EXECUTION_STATUS => AgentExecutionStatus::RUNNING,
			AgentToolLoopContextKeys::PHASE => $pendingCalls === []
				? AgentToolLoopContextKeys::PHASE_AFTER_TOOLS
				: AgentToolLoopContextKeys::PHASE_TOOLS
		], ['approved' => $approvedCount, 'denied' => $deniedCount, 'submitted' => $submittedCount]);
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

	private function failure(string $code, string $message): AgentStageResult {
		return AgentStageResult::patch([
			AgentToolLoopContextKeys::FAILURE_CODE => $code,
			AgentToolLoopContextKeys::FAILURE_MESSAGE => $message,
			AgentToolLoopContextKeys::FAILURE_DETAIL => [],
			AgentToolLoopContextKeys::EXECUTION_STATUS => AgentExecutionStatus::FAILED,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FAILED,
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::SUSPENDED => false
		]);
	}
}
