<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator\Stage;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentActionDecision;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentInteractionRequest;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\Stage\AgentActionReviewStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys as Keys;
use PHPUnit\Framework\TestCase;

final class AgentActionReviewStageTest extends TestCase {

	public function testStageIdentityAndEligibilityRequirePendingReview(): void {
		$stage = $this->stage();
		$this->assertSame('agentactionreviewstage', AgentActionReviewStage::getName());
		$this->assertSame('action-review', $stage->id());
		$this->assertSame(IAgentStage::AI_USAGE_NONE, $stage->getAiUsage());
		$base = [Keys::PHASE => Keys::PHASE_TOOLS, Keys::ACTION_REVIEW_CANDIDATES => [$this->candidate(AgentActionDecision::requireApproval('a1', 'Check'))]];
		$this->assertTrue($stage->supports($this->context($base)));
		$this->assertFalse($stage->supports($this->context($base + [Keys::SUSPENDED => true])));
		$this->assertFalse($stage->supports($this->context($base + [Keys::FAILURE_CODE => 'existing'])));
		$this->assertFalse($stage->supports($this->context([Keys::PHASE => Keys::PHASE_AFTER_TOOLS, Keys::ACTION_REVIEW_CANDIDATES => $base[Keys::ACTION_REVIEW_CANDIDATES]])));
		$this->assertFalse($stage->supports($this->context([Keys::PHASE => Keys::PHASE_TOOLS, Keys::ACTION_REVIEW_CANDIDATES => []])));
	}

	public function testEmptyOrMalformedCandidateCollectionDoesNotSuspend(): void {
		$this->assertTrue($this->stage()->process($this->context([]))->isEmpty());
		$this->assertTrue($this->stage()->process($this->context([Keys::ACTION_REVIEW_CANDIDATES => 'bad']))->isEmpty());
	}

	public function testInvalidCandidateFailsBeforeGeneratingSuspension(): void {
		foreach ([false, [], ['action' => new AgentAction('a1', 'tool_call', 'read'), 'decision' => 'approve', 'tool_call' => null]] as $bad) {
			$patch = $this->stage()->process($this->context([Keys::ACTION_REVIEW_CANDIDATES => [$bad]]))->getPatch();
			$this->assertSame('invalid_action_review_candidate', $patch[Keys::FAILURE_CODE]);
			$this->assertSame(Keys::PHASE_FAILED, $patch[Keys::PHASE]);
			$this->assertSame(AgentExecutionStatus::FAILED, $patch[Keys::EXECUTION_STATUS]);
			$this->assertArrayNotHasKey(Keys::SUSPENSION, $patch);
		}
	}

	public function testApprovalBuildsResumableRequestWithConsistentActionFingerprint(): void {
		$candidate = $this->candidate(AgentActionDecision::requireApproval('a1', 'Need authorization'));
		$result = $this->stage()->process($this->context([
			Keys::ACTION_REVIEW_CANDIDATES => [$candidate],
			Keys::ITERATION => 4,
			Keys::CALL_INDEX => 7,
			Keys::NODE_ID => 'node-1',
			Keys::MESSAGES => [['role' => 'user', 'content' => 'Do this']],
			Keys::TOOL_RESULTS => [AgentToolResult::success('read-1', 'read', [], 'value')]
		]));
		$patch = $result->getPatch();
		$this->assertSame(AgentExecutionStatus::AWAITING_APPROVAL, $patch[Keys::EXECUTION_STATUS]);
		$this->assertSame(Keys::PHASE_AWAITING_APPROVAL, $patch[Keys::PHASE]);
		$this->assertSame(true, $patch[Keys::SUSPENDED]);
		$this->assertSame(Keys::FINAL_RESPONSE_NONE, $patch[Keys::FINAL_RESPONSE_MODE]);
		$request = $patch[Keys::INTERACTION_REQUESTS][0];
		$this->assertSame(AgentInteractionRequest::KIND_APPROVAL, $request->getKind());
		$this->assertSame('Need authorization', $request->getMessage());
		$this->assertSame($candidate['tool_call']->toArray(), $request->getMetadata()['tool_call']);
		$this->assertSame((new AgentActionFingerprint())->create($candidate['action']), $request->getActionFingerprint());
		$this->assertSame($request, $patch[Keys::SUSPENSION]->getRequests()[0]);
		$this->assertSame(4, $patch[Keys::SUSPENSION]->getState()['iteration']);
		$this->assertSame(7, $patch[Keys::SUSPENSION]->getState()['call_index']);
		$this->assertSame('node-1', $patch[Keys::SUSPENSION]->getMetadata()['node_id']);
		$this->assertSame('read-1', $patch[Keys::SUSPENSION]->getState()['tool_results'][0]['call_id']);
		$this->assertSame(1, $result->getMetadata()['request_count']);
	}

	public function testClarificationRequestPreservesExplicitInteractionMetadata(): void {
		$decision = AgentActionDecision::requireClarification('a1', 'Question', ['interaction' => [
			'title' => '  Which resource? ', 'message' => ' Specify the item ', 'risk' => 'high', 'summary' => ['item' => 'unknown']
		]]);
		$patch = $this->stage()->process($this->context([Keys::ACTION_REVIEW_CANDIDATES => [$this->candidate($decision)]]))->getPatch();
		$this->assertSame(AgentExecutionStatus::AWAITING_INPUT, $patch[Keys::EXECUTION_STATUS]);
		$this->assertSame(Keys::PHASE_AWAITING_INPUT, $patch[Keys::PHASE]);
		$request = $patch[Keys::INTERACTION_REQUESTS][0];
		$this->assertSame(AgentInteractionRequest::KIND_CLARIFICATION, $request->getKind());
		$this->assertSame('Which resource?', $request->getTitle());
		$this->assertSame('Specify the item', $request->getMessage());
		$this->assertSame('high', $request->getRisk());
		$this->assertSame(['item' => 'unknown'], $request->getSummary());
	}

	public function testDryRunRequiresInputAndUsesSafeDefaultLabelsForBlankMetadata(): void {
		$decision = AgentActionDecision::requireDryRun('a1', '', ['interaction' => ['title' => ' ', 'message' => ' ', 'risk' => ' ', 'summary' => 'bad']]);
		$patch = $this->stage()->process($this->context([Keys::ACTION_REVIEW_CANDIDATES => [$this->candidate($decision)]]))->getPatch();
		$request = $patch[Keys::INTERACTION_REQUESTS][0];
		$this->assertSame(AgentInteractionRequest::KIND_DRY_RUN, $request->getKind());
		$this->assertSame(AgentExecutionStatus::AWAITING_INPUT, $patch[Keys::EXECUTION_STATUS]);
		$this->assertSame('Review tool action', $request->getTitle());
		$this->assertStringContainsString('Explicit user input', $request->getMessage());
		$this->assertSame('medium', $request->getRisk());
		$this->assertSame(['tool' => 'create_resource', 'input' => ['path' => 'a']], $request->getSummary());
	}

	private function stage(): AgentActionReviewStage {
		return new AgentActionReviewStage(new AgentActionFingerprint());
	}

	private function candidate(AgentActionDecision $decision): array {
		return [
			'action' => new AgentAction('a1', AgentAction::TYPE_TOOL_CALL, 'create_resource', ['path' => 'a']),
			'decision' => $decision,
			'tool_call' => new AiToolCall('a1', 'create_resource', ['path' => 'a'])
		];
	}

	/** @param array<string,mixed> $values */
	private function context(array $values): IAgentContext {
		$context = $this->createStub(IAgentContext::class);
		$context->method('getVar')->willReturnCallback(static fn(string $key): mixed => $values[$key] ?? null);
		return $context;
	}
}
