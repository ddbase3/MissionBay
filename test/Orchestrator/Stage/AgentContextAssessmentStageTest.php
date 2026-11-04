<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator\Stage;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Dto\AgentContextAssessment;
use AssistantFoundation\Dto\AgentToolResult;
use MissionBay\Orchestrator\Stage\AgentContextAssessmentStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys as Keys;
use PHPUnit\Framework\TestCase;

final class AgentContextAssessmentStageTest extends TestCase {

	public function testStageIdentityAndUsageClassification(): void {
		$stage = new AgentContextAssessmentStage('assessment-id', 'Assessment name');
		$this->assertSame('agentcontextassessmentstage', AgentContextAssessmentStage::getName());
		$this->assertSame('assessment-id', $stage->id());
		$this->assertSame('Assessment name', $stage->name());
		$this->assertSame(IAgentStage::AI_USAGE_NONE, $stage->getAiUsage());
		$this->assertStringContainsString('provider', $stage->getDescription());
	}

	public function testSupportsOnlyUnfinishedSuccessfulAfterToolPhaseWithResults(): void {
		$stage = new AgentContextAssessmentStage();
		$base = [Keys::PHASE => Keys::PHASE_AFTER_TOOLS, Keys::TOOL_RESULTS => [AgentToolResult::success('a', 'read', [], 'yes')]];
		$this->assertTrue($stage->supports($this->context($base)));
		$this->assertFalse($stage->supports($this->context($base + [Keys::COMPLETED => true])));
		$this->assertFalse($stage->supports($this->context($base + [Keys::FAILURE_CODE => 'no_access'])));
		$this->assertFalse($stage->supports($this->context([Keys::PHASE => Keys::PHASE_MODEL, Keys::TOOL_RESULTS => $base[Keys::TOOL_RESULTS]])));
		$this->assertFalse($stage->supports($this->context([Keys::PHASE => Keys::PHASE_AFTER_TOOLS, Keys::TOOL_RESULTS => []])));
		$this->assertFalse($stage->supports($this->context([Keys::PHASE => Keys::PHASE_AFTER_TOOLS, Keys::TOOL_RESULTS => null])));
	}

	public function testMeasuresMessagesToolResultsAndReportedUsageWithoutModifyingMessages(): void {
		$success = AgentToolResult::success('a', 'find', ['q' => 'x'], ['value' => 'Ä']);
		$failure = AgentToolResult::failure('b', 'update', [], 'denied', 'Not allowed');
		$messages = ['text', 123, false, null, ['content' => 'ä']];
		$existing = new AgentContextAssessment(0, 0, 0, 0, 0, 0, 0);
		$stage = new AgentContextAssessmentStage();
		$result = $stage->process($this->context([
			Keys::MESSAGES => $messages,
			Keys::TOOL_RESULTS => [$success, $failure],
			Keys::MODEL_RESULTS => [
				['usage' => ['input_tokens' => 8, 'output_tokens' => 3, 'total_tokens' => 11, 'metrics' => ['price' => 0.25]]],
				['usage' => ['input_tokens' => '4', 'output_tokens' => 2, 'total_tokens' => 6, 'metrics' => ['price' => 0.75]]]
			],
			Keys::CONTEXT_ASSESSMENTS => [$existing],
			Keys::ITERATION => 5
		]));
		$patch = $result->getPatch();
		$this->assertSame([Keys::CONTEXT_ASSESSMENTS], array_keys($patch));
		$this->assertSame($existing, $patch[Keys::CONTEXT_ASSESSMENTS][0]);
		$assessment = $patch[Keys::CONTEXT_ASSESSMENTS][1];
		$this->assertSame(5, $assessment->getIteration());
		$this->assertSame(5, $assessment->getMessageCount());
		$this->assertSame(strlen('text') + strlen('123') + strlen('') + strlen('{"content":"ä"}'), $assessment->getMessageBytes());
		$this->assertSame(2, $assessment->getToolResultCount());
		$this->assertSame(1, $assessment->getSuccessfulToolResultCount());
		$this->assertSame(1, $assessment->getFailedToolResultCount());
		$expectedBytes = strlen((string)json_encode($success->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
			+ strlen((string)json_encode($failure->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		$this->assertSame($expectedBytes, $assessment->getToolResultBytes());
		$this->assertSame($assessment->getMessageBytes() + $expectedBytes, $assessment->getTotalMeasuredBytes());
		$this->assertSame(12, $assessment->getUsage()->getInputTokens());
		$this->assertSame(5, $assessment->getUsage()->getOutputTokens());
		$this->assertSame(17, $assessment->getUsage()->getTotalTokens());
		$this->assertSame(1.0, $assessment->getUsage()->toArray()['metrics']['price']);
		$this->assertSame(['phase' => Keys::PHASE_AFTER_TOOLS], $assessment->getMetadata());
	}

	public function testMissingOrMalformedOptionalArraysAreNormalizedToEmptyAssessment(): void {
		$assessment = (new AgentContextAssessmentStage())->process($this->context([
			Keys::MESSAGES => 'not-messages',
			Keys::TOOL_RESULTS => 'not-results',
			Keys::MODEL_RESULTS => false,
			Keys::CONTEXT_ASSESSMENTS => 'not-assessments'
		]))->getPatch()[Keys::CONTEXT_ASSESSMENTS][0];
		$this->assertSame(0, $assessment->getIteration());
		$this->assertSame(0, $assessment->getMessageCount());
		$this->assertSame(0, $assessment->getMessageBytes());
		$this->assertSame(0, $assessment->getToolResultCount());
		$this->assertSame(0, $assessment->getTotalMeasuredBytes());
		$this->assertSame(null, $assessment->getUsage()->getTotalTokens());
	}

	public function testInvalidToolResultFailsClosedWithoutRecordingAnAssessment(): void {
		$patch = (new AgentContextAssessmentStage())->process($this->context([
			Keys::TOOL_RESULTS => [AgentToolResult::success('a', 'read', [], 'ok'), ['status' => 'success']],
			Keys::CONTEXT_ASSESSMENTS => ['previous']
		]))->getPatch();
		$this->assertSame('invalid_tool_result', $patch[Keys::FAILURE_CODE]);
		$this->assertSame(Keys::PHASE_FAILED, $patch[Keys::PHASE]);
		$this->assertSame(false, $patch[Keys::COMPLETED]);
		$this->assertSame('array', $patch[Keys::FAILURE_DETAIL]['type']);
		$this->assertArrayNotHasKey(Keys::CONTEXT_ASSESSMENTS, $patch);
	}

	public function testInvalidModelResultUsageIsIgnoredInsteadOfInventingTokenEstimates(): void {
		$assessment = (new AgentContextAssessmentStage())->process($this->context([
			Keys::TOOL_RESULTS => [AgentToolResult::success('a', 'read', [], 'ok')],
			Keys::MODEL_RESULTS => [null, false, [], ['usage' => 'not-an-array'], ['usage' => ['input_tokens' => 'invalid', 'total_tokens' => 0]]]
		]))->getPatch()[Keys::CONTEXT_ASSESSMENTS][0];
		$this->assertNull($assessment->getUsage()->getInputTokens());
		$this->assertSame(0, $assessment->getUsage()->getTotalTokens());
	}

	/** @param array<string,mixed> $values */
	private function context(array $values): IAgentContext {
		$context = $this->createStub(IAgentContext::class);
		$context->method('getVar')->willReturnCallback(static fn(string $key): mixed => $values[$key] ?? null);
		return $context;
	}
}
