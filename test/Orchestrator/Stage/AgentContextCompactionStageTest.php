<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator\Stage;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentContextAssessment;
use AssistantFoundation\Dto\AgentContextCompaction;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AiResultMetadata;
use MissionBay\Orchestrator\Stage\AgentContextCompactionStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys as Keys;
use PHPUnit\Framework\TestCase;

final class AgentContextCompactionStageTest extends TestCase {

	public function testStageIdentityAndEligibilityFollowToolPhaseContract(): void {
		$stage = new AgentContextCompactionStage();
		$this->assertSame('agentcontextcompactionstage', AgentContextCompactionStage::getName());
		$this->assertSame('context-compaction', $stage->id());
		$this->assertSame(IAgentStage::AI_USAGE_CONDITIONAL, $stage->getAiUsage());
		$base = [Keys::PHASE => Keys::PHASE_AFTER_TOOLS, Keys::TOOL_RESULTS => [AgentToolResult::success('a', 'read', [], 'some text')]];
		$this->assertTrue($stage->supports($this->context($base)));
		$this->assertFalse($stage->supports($this->context($base + [Keys::COMPLETED => true])));
		$this->assertFalse($stage->supports($this->context($base + [Keys::FAILURE_CODE => 'error'])));
		$this->assertFalse($stage->supports($this->context([Keys::PHASE => Keys::PHASE_MODEL, Keys::TOOL_RESULTS => $base[Keys::TOOL_RESULTS]])));
		$this->assertFalse($stage->supports($this->context([Keys::PHASE => Keys::PHASE_AFTER_TOOLS, Keys::TOOL_RESULTS => []])));
	}

	public function testSmallSuccessfulOutputIsAssessedWithoutAdditionalModelCall(): void {
		$tool = AgentToolResult::success('a', 'read', [], 'tiny');
		$model = $this->createMock(IAiChatModel::class);
		$model->expects($this->never())->method('complete');
		$patch = $this->stage(100)->process($this->context([
			Keys::MODEL => $model,
			Keys::TOOL_RESULTS => [$tool],
			Keys::MESSAGES => ['question']
		]))->getPatch();
		$this->assertCount(1, $patch[Keys::CONTEXT_ASSESSMENTS]);
		$this->assertSame(1, $patch[Keys::CONTEXT_ASSESSMENTS][0]->getToolResultCount());
		$this->assertArrayNotHasKey(Keys::TOOL_RESULTS, $patch);
		$this->assertArrayNotHasKey(Keys::CONTEXT_COMPACTIONS, $patch);
	}

	public function testLargeFailedAndTerminalAnswerResultsNeverRequestCompaction(): void {
		$failed = AgentToolResult::failure('a', 'write', [], 'denied', str_repeat('error', 20), [], str_repeat('x', 100));
		$terminal = AgentToolResult::success('b', 'search', [], ['final_answer_ready' => true, 'answer' => str_repeat('fact', 30)]);
		$model = $this->createMock(IAiChatModel::class);
		$model->expects($this->never())->method('complete');
		$patch = $this->stage(10)->process($this->context([Keys::MODEL => $model, Keys::TOOL_RESULTS => [$failed, $terminal]]))->getPatch();
		$this->assertSame(1, $patch[Keys::CONTEXT_ASSESSMENTS][0]->getSuccessfulToolResultCount());
		$this->assertSame(1, $patch[Keys::CONTEXT_ASSESSMENTS][0]->getFailedToolResultCount());
		$this->assertArrayNotHasKey(Keys::TOOL_RESULTS, $patch);
		$this->assertArrayNotHasKey(Keys::CONTEXT_COMPACTIONS, $patch);
	}

	public function testMissingChatModelFailsInsteadOfSilentlyReturningUncompactedOutput(): void {
		$patch = $this->stage(5)->process($this->context([
			Keys::TOOL_RESULTS => [AgentToolResult::success('a', 'read', [], str_repeat('x', 100))]
		]))->getPatch();
		$this->assertSame('stage_runtime_error', $patch[Keys::FAILURE_CODE]);
		$this->assertSame(Keys::PHASE_FAILED, $patch[Keys::PHASE]);
		$this->assertCount(1, $patch[Keys::CONTEXT_ASSESSMENTS]);
	}

	public function testInvalidToolResultStopsBeforeModelCompaction(): void {
		$patch = $this->stage(5)->process($this->context([Keys::TOOL_RESULTS => [true]]))->getPatch();
		$this->assertSame('invalid_tool_result', $patch[Keys::FAILURE_CODE]);
		$this->assertSame(Keys::PHASE_FAILED, $patch[Keys::PHASE]);
		$this->assertArrayNotHasKey(Keys::CONTEXT_COMPACTIONS, $patch);
	}

	public function testLargeOutputIsCompactedAndOriginalCallIdentityIsPreserved(): void {
		$tool = AgentToolResult::success('call-1', 'read', ['path' => 'x'], str_repeat('A', 50), ['source' => 'index']);
		$seenMessages = [];
		$model = $this->createStub(IAiChatModel::class);
		$model->method('complete')->willReturnCallback(static function(array $messages, array $tools) use (&$seenMessages): AiChatResult {
			$seenMessages = $messages;
			return new AiChatResult('  Key facts summarized.  ', [], new AiResultMetadata('chat', 'test-provider'));
		});
		$patch = $this->stage(10, 20)->process($this->context([
			Keys::MODEL => $model,
			Keys::TOOL_RESULTS => [$tool],
			Keys::ITERATION => 2
		]))->getPatch();
		$compacted = $patch[Keys::TOOL_RESULTS][0];
		$this->assertSame('call-1', $compacted->getCallId());
		$this->assertSame('read', $compacted->getToolName());
		$this->assertSame(['path' => 'x'], $compacted->getArguments());
		$this->assertSame('Key facts summarized.', $compacted->getOutput());
		$this->assertSame('index', $compacted->getMetadata()['source']);
		$this->assertSame(true, $compacted->getMetadata()['compaction']['applied']);
		$this->assertSame(true, $compacted->getMetadata()['compaction']['input_truncated']);
		$this->assertSame(50, $compacted->getMetadata()['compaction']['original_bytes']);
		$this->assertStringContainsString('truncated', $seenMessages[1]['content']);
		$this->assertSame(20, strlen(substr($seenMessages[1]['content'], strrpos($seenMessages[1]['content'], 'Tool output:') + strlen('Tool output:') + 1)));
		$this->assertSame(2, $patch[Keys::CONTEXT_COMPACTIONS][0]->getIteration());
		$this->assertTrue($patch[Keys::CONTEXT_COMPACTIONS][0]->wasApplied());
		$this->assertSame('test-provider', $patch[Keys::MODEL_RESULTS][0]['provider']);
	}

	public function testFailedCompactionPreservesOriginalResultAndRecordsFailure(): void {
		$tool = AgentToolResult::success('call-1', 'read', [], str_repeat('A', 60));
		$model = $this->createStub(IAiChatModel::class);
		$model->method('complete')->willThrowException(new \RuntimeException('offline'));
		$patch = $this->stage(10)->process($this->context([Keys::MODEL => $model, Keys::TOOL_RESULTS => [$tool]]))->getPatch();
		$this->assertSame($tool, $patch[Keys::TOOL_RESULTS][0]);
		$this->assertFalse($patch[Keys::CONTEXT_COMPACTIONS][0]->wasApplied());
		$this->assertSame('offline', $patch[Keys::CONTEXT_COMPACTIONS][0]->getErrorMessage());
		$this->assertSame(60, $patch[Keys::CONTEXT_COMPACTIONS][0]->getCompactedBytes());
		$this->assertSame([], $patch[Keys::MODEL_RESULTS]);
	}

	private function stage(int $minBytes, int $maxInputBytes = 80): AgentContextCompactionStage {
		return new AgentContextCompactionStage(minToolResultBytes: $minBytes, maxInputBytes: $maxInputBytes);
	}

	/** @param array<string,mixed> $values */
	private function context(array $values): IAgentContext {
		$context = $this->createStub(IAgentContext::class);
		$context->method('getVar')->willReturnCallback(static function(string $key) use (&$values): mixed { return $values[$key] ?? null; });
		$context->method('setVar')->willReturnCallback(static function(string $key, mixed $value) use (&$values): void { $values[$key] = $value; });
		return $context;
	}
}
