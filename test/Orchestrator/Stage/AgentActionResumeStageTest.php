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

namespace Base3\MissionBay\Test\Orchestrator\Stage;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentStage;
use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentExecutionStatus;
use Base3\AssistantFoundation\Dto\AgentInteractionRequest;
use Base3\AssistantFoundation\Dto\AgentInteractionResponse;
use Base3\AssistantFoundation\Dto\AgentResume;
use Base3\AssistantFoundation\Dto\AgentSuspension;
use Base3\AssistantFoundation\Dto\AgentSuspensionClaim;
use Base3\AssistantFoundation\Dto\AiToolCall;
use Base3\MissionBay\Dto\Assistant\PreparedAgentResume;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\Stage\AgentActionResumeStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys as Keys;
use PHPUnit\Framework\TestCase;

final class AgentActionResumeStageTest extends TestCase {

	public function testStageMetadataAndSupportRequirePreparedResumePhase(): void {
		$stage = $this->stage();
		$prepared = $this->prepared([], [$this->request()]);
		$this->assertSame('agentactionresumestage', AgentActionResumeStage::getName());
		$this->assertSame('action-resume', $stage->id());
		$this->assertSame('action-resume', $stage->name());
		$this->assertSame(IAgentStage::AI_USAGE_NONE, $stage->getAiUsage());
		$this->assertTrue($stage->supports($this->context([Keys::PHASE => Keys::PHASE_RESUME, Keys::RESUME => $prepared])));
		$this->assertFalse($stage->supports($this->context([Keys::PHASE => Keys::PHASE_MODEL, Keys::RESUME => $prepared])));
		$this->assertFalse($stage->supports($this->context([Keys::PHASE => Keys::PHASE_RESUME, Keys::RESUME => 'untrusted'])));
		$this->assertFalse($stage->supports($this->context([Keys::PHASE => Keys::PHASE_RESUME, Keys::RESUME => $prepared, Keys::FAILURE_CODE => 'existing_failure'])));
	}

	public function testMissingPreparedPayloadFailsClosed(): void {
		$patch = $this->stage()->process($this->context([Keys::RESUME => ['handle' => 'untrusted']]))->getPatch();
		$this->assertFailure($patch, 'invalid_agent_resume');
	}

	public function testMissingDecisionCannotImplicitlyApprovePendingAction(): void {
		$patch = $this->stage()->process($this->context([Keys::RESUME => $this->prepared([], [$this->request()])]))->getPatch();
		$this->assertFailure($patch, 'missing_agent_resume_response');
	}

	public function testDuplicateResponseToSameRequestIsRejected(): void {
		$responses = [new AgentInteractionResponse('request-1', AgentInteractionResponse::DECISION_APPROVE), new AgentInteractionResponse('request-1', AgentInteractionResponse::DECISION_DENY)];
		$patch = $this->stage()->process($this->context([Keys::RESUME => $this->prepared($responses, [$this->request()])]))->getPatch();
		$this->assertFailure($patch, 'duplicate_agent_resume_response');
	}

	public function testUnknownResponseDoesNotAuthorizeUnmatchedAction(): void {
		$responses = [new AgentInteractionResponse('request-1', AgentInteractionResponse::DECISION_DENY), new AgentInteractionResponse('unknown', AgentInteractionResponse::DECISION_APPROVE)];
		$patch = $this->stage()->process($this->context([Keys::RESUME => $this->prepared($responses, [$this->request()])]))->getPatch();
		$this->assertFailure($patch, 'unknown_agent_resume_response');
	}

	public function testTamperedReviewedActionFingerprintIsRejected(): void {
		$request = $this->request(AgentInteractionRequest::KIND_APPROVAL, str_repeat('f', 64));
		$patch = $this->stage()->process($this->context([Keys::RESUME => $this->prepared([
			new AgentInteractionResponse('request-1', AgentInteractionResponse::DECISION_APPROVE)
		], [$request])]))->getPatch();
		$this->assertFailure($patch, 'invalid_agent_resume_snapshot');
	}

	public function testMissingOrMismatchedToolCallIsRejectedEvenWithValidFingerprint(): void {
		$original = $this->request();
		$withoutToolCall = new AgentInteractionRequest('request-1', AgentInteractionRequest::KIND_APPROVAL, $original->getAction(), $original->getActionFingerprint(), 'Title', 'Message');
		$mismatchedToolCall = $this->request(AgentInteractionRequest::KIND_APPROVAL, null, new AiToolCall('different', 'create_resource', ['path' => 'a']));
		foreach ([$withoutToolCall, $mismatchedToolCall] as $request) {
			$patch = $this->stage()->process($this->context([Keys::RESUME => $this->prepared([
				new AgentInteractionResponse('request-1', AgentInteractionResponse::DECISION_APPROVE)
			], [$request])]))->getPatch();
			$this->assertFailure($patch, 'invalid_agent_resume_snapshot');
		}
	}

	public function testClarificationCannotBeApprovedAsAnApprovalRequest(): void {
		$prepared = $this->prepared([
			new AgentInteractionResponse('request-1', AgentInteractionResponse::DECISION_APPROVE)
		], [$this->request(AgentInteractionRequest::KIND_CLARIFICATION)]);
		$patch = $this->stage()->process($this->context([Keys::RESUME => $prepared]))->getPatch();
		$this->assertFailure($patch, 'invalid_agent_resume_decision');
	}

	public function testExplicitApprovalRequeuesExactActionAndRecordsItsFingerprint(): void {
		$request = $this->request();
		$prepared = $this->prepared([new AgentInteractionResponse('request-1', AgentInteractionResponse::DECISION_APPROVE)], [$request]);
		$previous = new AiToolCall('previous', 'read', []);
		$patchResult = $this->stage()->process($this->context([
			Keys::RESUME => $prepared,
			Keys::PENDING_TOOL_CALLS => [$previous],
			Keys::PREAPPROVED_ACTIONS => ['previous' => 'old-hash'],
			Keys::ACTION_REVIEW_CANDIDATES => ['stale'],
			Keys::INTERACTION_REQUESTS => [$request]
		]));
		$patch = $patchResult->getPatch();
		$this->assertSame(Keys::PHASE_TOOLS, $patch[Keys::PHASE]);
		$this->assertSame(AgentExecutionStatus::RUNNING, $patch[Keys::EXECUTION_STATUS]);
		$this->assertSame($previous, $patch[Keys::PENDING_TOOL_CALLS][0]);
		$this->assertSame('action-1', $patch[Keys::PENDING_TOOL_CALLS][1]->getId());
		$this->assertSame(['path' => 'a'], $patch[Keys::PENDING_TOOL_CALLS][1]->getArguments());
		$this->assertSame($request->getActionFingerprint(), $patch[Keys::PREAPPROVED_ACTIONS]['action-1']);
		$this->assertSame('old-hash', $patch[Keys::PREAPPROVED_ACTIONS]['previous']);
		$this->assertSame([], $patch[Keys::ACTION_REVIEW_CANDIDATES]);
		$this->assertSame([], $patch[Keys::INTERACTION_REQUESTS]);
		$this->assertNull($patch[Keys::SUSPENSION]);
		$this->assertFalse($patch[Keys::SUSPENDED]);
		$this->assertSame(['approved' => 1, 'denied' => 0, 'submitted' => 0], $patchResult->getMetadata());
	}

	public function testDenyProducesBlockedToolResultWithoutExecutingPendingAction(): void {
		$prepared = $this->prepared([new AgentInteractionResponse('request-1', AgentInteractionResponse::DECISION_DENY, [], 'Not for this project')], [$this->request()]);
		$patchResult = $this->stage()->process($this->context([Keys::RESUME => $prepared]));
		$patch = $patchResult->getPatch();
		$this->assertSame(Keys::PHASE_AFTER_TOOLS, $patch[Keys::PHASE]);
		$this->assertSame([], $patch[Keys::PENDING_TOOL_CALLS]);
		$this->assertSame([], $patch[Keys::PREAPPROVED_ACTIONS]);
		$this->assertSame('action_declined_by_user', $patch[Keys::TOOL_RESULTS][0]->getErrorCode());
		$this->assertSame('Not for this project', $patch[Keys::TOOL_RESULTS][0]->getErrorMessage());
		$this->assertSame('deny', $patch[Keys::TOOL_RESULTS][0]->getOutput()['decision']);
		$this->assertSame('request-1', $patch[Keys::TOOL_RESULTS][0]->getMetadata()['interaction_request_id']);
		$this->assertSame(['approved' => 0, 'denied' => 1, 'submitted' => 0], $patchResult->getMetadata());
	}

	public function testSubmitUsesOnlyExplicitReplacementInputWithoutAutoApproval(): void {
		$prepared = $this->prepared([new AgentInteractionResponse('request-1', AgentInteractionResponse::DECISION_SUBMIT, ['path' => 'new-path'])], [$this->request(AgentInteractionRequest::KIND_CLARIFICATION)]);
		$patchResult = $this->stage()->process($this->context([Keys::RESUME => $prepared]));
		$patch = $patchResult->getPatch();
		$this->assertSame(Keys::PHASE_TOOLS, $patch[Keys::PHASE]);
		$this->assertSame(['path' => 'new-path'], $patch[Keys::PENDING_TOOL_CALLS][0]->getArguments());
		$this->assertSame('request-1', $patch[Keys::PENDING_TOOL_CALLS][0]->getMetadata()['resumed_from_interaction']);
		$this->assertSame([], $patch[Keys::PREAPPROVED_ACTIONS]);
		$this->assertSame(['approved' => 0, 'denied' => 0, 'submitted' => 1], $patchResult->getMetadata());
	}

	private function stage(): AgentActionResumeStage {
		return new AgentActionResumeStage(new AgentActionFingerprint());
	}

	private function request(string $kind = AgentInteractionRequest::KIND_APPROVAL, ?string $fingerprint = null, ?AiToolCall $toolCall = null): AgentInteractionRequest {
		$action = new AgentAction('action-1', AgentAction::TYPE_TOOL_CALL, 'create_resource', ['path' => 'a']);
		$fingerprint ??= (new AgentActionFingerprint())->create($action);
		$toolCall ??= new AiToolCall('action-1', 'create_resource', ['path' => 'a']);
		return new AgentInteractionRequest('request-1', $kind, $action, $fingerprint, 'Title', 'Message', [], 'medium', ['tool_call' => $toolCall->toArray()]);
	}

	/** @param array<int,AgentInteractionResponse> $responses @param array<int,AgentInteractionRequest> $requests */
	private function prepared(array $responses, array $requests): PreparedAgentResume {
		$suspension = new AgentSuspension('suspension-1', AgentExecutionStatus::AWAITING_APPROVAL, $requests, [], '2026-01-01T00:00:00+00:00');
		$claim = new AgentSuspensionClaim('opaque-handle', 'claim-token', $suspension);
		return new PreparedAgentResume(new AgentResume('opaque-handle', $responses), $claim);
	}

	/** @param array<string,mixed> $patch */
	private function assertFailure(array $patch, string $code): void {
		$this->assertSame($code, $patch[Keys::FAILURE_CODE]);
		$this->assertSame(Keys::PHASE_FAILED, $patch[Keys::PHASE]);
		$this->assertSame(AgentExecutionStatus::FAILED, $patch[Keys::EXECUTION_STATUS]);
		$this->assertSame(false, $patch[Keys::COMPLETED]);
		$this->assertSame(false, $patch[Keys::SUSPENDED]);
	}

	/** @param array<string,mixed> $values */
	private function context(array $values): IAgentContext {
		$context = $this->createStub(IAgentContext::class);
		$context->method('getVar')->willReturnCallback(static fn(string $key): mixed => $values[$key] ?? null);
		return $context;
	}
}
