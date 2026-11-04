<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentEventSink;
use AssistantFoundation\Api\IAgentSuspensionRepository;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentActionDecision;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentInteractionRequest;
use AssistantFoundation\Dto\AgentSuspension;
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\Service\AgentActionReviewService;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys as Keys;
use PHPUnit\Framework\TestCase;

final class AgentActionReviewServiceTest extends TestCase {

	public function testInvalidSuspensionTtlIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		new AgentActionReviewService(new AgentActionFingerprint(), $this->createStub(IAgentSuspensionRepository::class), 0);
	}

	public function testNoCandidatesDoesNotPersistOrPatchState(): void {
		$repository = $this->createMock(IAgentSuspensionRepository::class);
		$repository->expects($this->never())->method('create');
		$service = $this->service($repository);
		$this->assertTrue($service->review($this->context([]))->isEmpty());
		$this->assertTrue($service->review($this->context([Keys::ACTION_REVIEW_CANDIDATES => []]))->isEmpty());
	}

	public function testCancellationClearsPendingApprovalsAndToolCallsWithoutSavingAnything(): void {
		$repository = $this->createMock(IAgentSuspensionRepository::class);
		$repository->expects($this->never())->method('create');
		$sink = $this->createStub(IAgentEventSink::class);
		$sink->method('isCancelled')->willReturn(true);
		$patch = $this->service($repository)->review($this->context([
			IAgentEventSink::CONTEXT_KEY => $sink,
			Keys::ACTION_REVIEW_CANDIDATES => [['unvalidated' => true]]
		]))->getPatch();
		$this->assertSame(AgentExecutionStatus::CANCELLED, $patch[Keys::EXECUTION_STATUS]);
		$this->assertSame([], $patch[Keys::ACTION_REVIEW_CANDIDATES]);
		$this->assertSame([], $patch[Keys::PENDING_TOOL_CALLS]);
		$this->assertSame('', $patch[Keys::RESUME_HANDLE]);
		$this->assertFalse($patch[Keys::SUSPENDED]);
	}

	public function testNonArrayReviewCandidateFailsWithoutPersistingSuspension(): void {
		$repository = $this->createMock(IAgentSuspensionRepository::class);
		$repository->expects($this->never())->method('create');
		$patch = $this->service($repository)->review($this->context([
			Keys::ACTION_REVIEW_CANDIDATES => [false]
		]))->getPatch();
		$this->assertSame('invalid_action_review_candidate', $patch[Keys::FAILURE_CODE]);
		$this->assertSame(Keys::PHASE_FAILED, $patch[Keys::PHASE]);
	}

	public function testInvalidCandidateRuntimeObjectsFailClosed(): void {
		$repository = $this->createStub(IAgentSuspensionRepository::class);
		$invalid = ['action' => 'not an action', 'decision' => AgentActionDecision::allow('a'), 'tool_call' => null];
		$patch = $this->service($repository)->review($this->context([
			Keys::ACTION_REVIEW_CANDIDATES => [$invalid]
		]))->getPatch();
		$this->assertSame('invalid_action_review_candidate', $patch[Keys::FAILURE_CODE]);
		$this->assertSame(AgentExecutionStatus::FAILED, $patch[Keys::EXECUTION_STATUS]);
	}

	public function testClarificationCreatesSuspensionWithOpaqueResumeHandle(): void {
		$created = [];
		$repository = $this->createStub(IAgentSuspensionRepository::class);
		$repository->method('create')->willReturnCallback(static function(AgentSuspension $suspension, int $ttl) use (&$created): string {
			$created = [$suspension, $ttl];
			return 'opaque-resume-handle';
		});
		$context = $this->context([
			Keys::ITERATION => 3,
			Keys::ACTION_REVIEW_CANDIDATES => [$this->candidate(AgentActionDecision::requireClarification('a1', 'Need more information', [
				'interaction' => ['risk' => 'high', 'title' => 'Confirm target']
			]))],
			'conversation_id' => 'chat-1', 'conversation_channel_id' => 'web'
		]);
		$result = $this->service($repository)->review($context);
		$patch = $result->getPatch();
		$this->assertSame('opaque-resume-handle', $patch[Keys::RESUME_HANDLE]);
		$this->assertSame(AgentExecutionStatus::AWAITING_INPUT, $patch[Keys::EXECUTION_STATUS]);
		$this->assertSame(Keys::PHASE_AWAITING_INPUT, $patch[Keys::PHASE]);
		$this->assertTrue($patch[Keys::SUSPENDED]);
		$this->assertSame(900, $created[1]);
		$this->assertSame(3, $created[0]->getState()['iteration']);
		$request = $patch[Keys::INTERACTION_REQUESTS][0];
		$this->assertSame(AgentInteractionRequest::KIND_CLARIFICATION, $request->getKind());
		$this->assertSame('high', $request->getRisk());
		$this->assertSame('Confirm target', $request->getTitle());
		$this->assertSame(AgentExecutionStatus::AWAITING_INPUT, $created[0]->getStatus());
	}

	public function testPersistenceFailureIsReturnedWithoutPretendingToSuspend(): void {
		$repository = $this->createStub(IAgentSuspensionRepository::class);
		$repository->method('create')->willThrowException(new \RuntimeException('storage offline'));
		$patch = $this->service($repository)->review($this->context([
			Keys::ACTION_REVIEW_CANDIDATES => [$this->candidate(AgentActionDecision::requireClarification('a1', 'Need input'))]
		]))->getPatch();
		$this->assertSame('suspension_persistence_failed', $patch[Keys::FAILURE_CODE]);
		$this->assertSame(Keys::PHASE_FAILED, $patch[Keys::PHASE]);
		$this->assertArrayNotHasKey(Keys::RESUME_HANDLE, $patch);
	}

	public function testProjectedCandidatesTakePrecedenceOverContextCandidates(): void {
		$repository = $this->createStub(IAgentSuspensionRepository::class);
		$patch = $this->service($repository)->review($this->context([
			Keys::ACTION_REVIEW_CANDIDATES => [false]
		]), [Keys::ACTION_REVIEW_CANDIDATES => []]);
		$this->assertTrue($patch->isEmpty());
	}

	private function candidate(AgentActionDecision $decision): array {
		return [
			'action' => new AgentAction('a1', AgentAction::TYPE_TOOL_CALL, 'fetch_data', ['path' => 'x']),
			'decision' => $decision,
			'tool_call' => new AiToolCall('a1', 'fetch_data', ['path' => 'x'])
		];
	}

	private function service(IAgentSuspensionRepository $repository): AgentActionReviewService {
		return new AgentActionReviewService(new AgentActionFingerprint(), $repository);
	}

	/** @param array<string,mixed> $values */
	private function context(array $values): IAgentContext {
		$context = $this->createStub(IAgentContext::class);
		$context->method('getVar')->willReturnCallback(static fn(string $key): mixed => $values[$key] ?? null);
		return $context;
	}
}
