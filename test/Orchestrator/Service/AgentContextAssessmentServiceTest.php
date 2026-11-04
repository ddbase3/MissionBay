<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentContextAssessment;
use AssistantFoundation\Dto\AgentToolResult;
use MissionBay\Orchestrator\Service\AgentContextAssessmentService;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys as Keys;
use PHPUnit\Framework\TestCase;

final class AgentContextAssessmentServiceTest extends TestCase {

	public function testCountsExactBytesResultsAndReportedUsage(): void {
		$success = AgentToolResult::success('ok', 'get', [], ['value' => 7]);
		$failure = AgentToolResult::failure('fail', 'get', [], 'missing', 'Not found');
		$messages = ['hi', true, 7, null, ['a' => 'ä']];
		$result = (new AgentContextAssessmentService())->assess($this->context([
			Keys::ITERATION => 3,
			Keys::MESSAGES => $messages,
			Keys::TOOL_RESULTS => [$success, $failure],
			Keys::MODEL_RESULTS => [
				['usage' => ['input_tokens' => 3, 'output_tokens' => 2]],
				['usage' => ['input_tokens' => '5']],
				['usage' => 'not an array'],
				'bad'
			]
		]));
		$record = $result->getPatch()[Keys::CONTEXT_ASSESSMENTS][0];
		$this->assertSame(3, $record->getIteration());
		$this->assertSame(5, $record->getMessageCount());
		$this->assertSame(2, $record->getToolResultCount());
		$this->assertSame(1, $record->getSuccessfulToolResultCount());
		$this->assertSame(1, $record->getFailedToolResultCount());
		$this->assertSame(2 + 1 + 1 + strlen(json_encode(['a' => 'ä'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), $record->getMessageBytes());
		$this->assertSame(strlen(json_encode($success->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) + strlen(json_encode($failure->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), $record->getToolResultBytes());
		$this->assertSame(8, $record->getUsage()->getInputTokens());
		$this->assertSame(2, $record->getUsage()->getOutputTokens());
		$this->assertSame(Keys::PHASE_AFTER_TOOLS, $record->getMetadata()['phase']);
	}

	public function testInvalidToolResultTransitionsToFailedStage(): void {
		$result = (new AgentContextAssessmentService())->assess($this->context([
			Keys::TOOL_RESULTS => [AgentToolResult::success('ok', 'read', [], 1), ['bad' => true]]
		]));
		$this->assertSame('invalid_tool_result', $result->getPatch()[Keys::FAILURE_CODE]);
		$this->assertSame(Keys::PHASE_FAILED, $result->getPatch()[Keys::PHASE]);
		$this->assertSame('array', $result->getPatch()[Keys::FAILURE_DETAIL]['type']);
		$this->assertArrayNotHasKey(Keys::CONTEXT_ASSESSMENTS, $result->getPatch());
	}

	public function testMissingCollectionsAreNormalizedAndEarlierAssessmentsPreserved(): void {
		$prior = new AgentContextAssessment(0, 0, 0, 0, 0, 0, 0);
		$result = (new AgentContextAssessmentService())->assess($this->context([
			Keys::MESSAGES => 'not a list',
			Keys::TOOL_RESULTS => null,
			Keys::MODEL_RESULTS => 1,
			Keys::CONTEXT_ASSESSMENTS => [$prior]
		]));
		$this->assertSame($prior, $result->getPatch()[Keys::CONTEXT_ASSESSMENTS][0]);
		$this->assertSame(0, $result->getPatch()[Keys::CONTEXT_ASSESSMENTS][1]->getTotalMeasuredBytes());
		$this->assertNull($result->getPatch()[Keys::CONTEXT_ASSESSMENTS][1]->getUsage()->getTotalTokens());
	}

	/** @param array<string,mixed> $values */
	private function context(array $values): IAgentContext {
		$context = $this->createStub(IAgentContext::class);
		$context->method('getVar')->willReturnCallback(static fn(string $key): mixed => $values[$key] ?? null);
		return $context;
	}
}
