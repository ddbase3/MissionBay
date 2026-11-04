<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator\Stage;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Dto\AgentToolResult;
use MissionBay\Orchestrator\Stage\AgentToolObservationStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys as Keys;
use PHPUnit\Framework\TestCase;

final class AgentToolObservationStageTest extends TestCase {

	public function testStageMetadataAndSupportRules(): void {
		$stage = new AgentToolObservationStage('observe', 'Read observations');
		$this->assertSame('agenttoolobservationstage', AgentToolObservationStage::getName());
		$this->assertSame('observe', $stage->id());
		$this->assertSame('Read observations', $stage->name());
		$this->assertSame(IAgentStage::AI_USAGE_NONE, $stage->getAiUsage());
		$this->assertFalse($stage->supports($this->context([Keys::PHASE => Keys::PHASE_AFTER_TOOLS, Keys::TOOL_RESULTS => []])));
		$this->assertTrue($stage->supports($this->context([Keys::PHASE => Keys::PHASE_AFTER_TOOLS, Keys::TOOL_RESULTS => [AgentToolResult::success('a', 'read', [], 'ok')]])));
		$this->assertFalse($stage->supports($this->context([Keys::PHASE => Keys::PHASE_TOOLS, Keys::TOOL_RESULTS => ['anything']])));
		$this->assertFalse($stage->supports($this->context([Keys::PHASE => Keys::PHASE_AFTER_TOOLS, Keys::TOOL_RESULTS => ['anything'], Keys::COMPLETED => true])));
		$this->assertFalse($stage->supports($this->context([Keys::PHASE => Keys::PHASE_AFTER_TOOLS, Keys::TOOL_RESULTS => ['anything'], Keys::FAILURE_CODE => 'denied'])));
	}

	public function testSuccessfulObservationPreservesMessageOrderAndClearsResults(): void {
		$first = AgentToolResult::success('c1', 'read', ['id' => 1], ['title' => 'Überblick']);
		$second = AgentToolResult::success('c2', 'search', [], 'plain content');
		$result = (new AgentToolObservationStage())->process($this->context([
			Keys::TOOL_RESULTS => [$first, $second],
			Keys::MESSAGES => [['role' => 'user', 'content' => 'query']],
			Keys::OBSERVATIONS => ['previous']
		]));
		$patch = $result->getPatch();
		$this->assertSame(Keys::PHASE_OBSERVED, $patch[Keys::PHASE]);
		$this->assertSame([], $patch[Keys::TOOL_RESULTS]);
		$this->assertSame(['previous', $first, $second], $patch[Keys::OBSERVATIONS]);
		$this->assertSame('c1', $patch[Keys::MESSAGES][1]['tool_call_id']);
		$this->assertSame('{"title":"Überblick"}', $patch[Keys::MESSAGES][1]['content']);
		$this->assertSame('plain content', $patch[Keys::MESSAGES][2]['content']);
	}

	public function testFailedToolWithNullOutputIsRenderedAsStructuredError(): void {
		$failed = AgentToolResult::failure('c1', 'delete', [], 'forbidden', 'Not authorized');
		$result = (new AgentToolObservationStage())->process($this->context([Keys::TOOL_RESULTS => [$failed]]));
		$payload = json_decode($result->getPatch()[Keys::MESSAGES][0]['content'], true);
		$this->assertSame(false, $payload['ok']);
		$this->assertSame('forbidden', $payload['error_code']);
		$this->assertSame('Not authorized', $payload['error']);
	}

	public function testSuccessfulNullOutputCreatesEmptyToolMessage(): void {
		$result = (new AgentToolObservationStage())->process($this->context([
			Keys::TOOL_RESULTS => [AgentToolResult::success('c1', 'read', [], null)]
		]));
		$this->assertSame('', $result->getPatch()[Keys::MESSAGES][0]['content']);
	}

	public function testExplicitTerminalToolEvidenceFinalizesAnswerWithoutAnotherToolCall(): void {
		$tool = AgentToolResult::success('c1', 'websearch', [], [
			'final_answer_ready' => true, 'answer' => 'Retrieved and cited text', 'sources' => ['example.test']
		]);
		$result = (new AgentToolObservationStage())->process($this->context([Keys::TOOL_RESULTS => [$tool]]));
		$patch = $result->getPatch();
		$this->assertTrue($patch[Keys::TERMINAL_EVIDENCE_READY]);
		$this->assertTrue($patch[Keys::COMPLETED]);
		$this->assertSame(Keys::PHASE_FINAL, $patch[Keys::PHASE]);
		$this->assertSame(Keys::FINAL_RESPONSE_COMPLETE, $patch[Keys::FINAL_RESPONSE_MODE]);
		$this->assertStringContainsString('supporting sources', $patch[Keys::FINAL_RESPONSE_INSTRUCTION]);
	}

	public function testMalformedTerminalEvidenceCannotEndTheLoop(): void {
		foreach ([
			['final_answer_ready' => true, 'answer' => ' '],
			['final_answer_ready' => false, 'answer' => 'text'],
			['final_answer_ready' => 'true', 'answer' => 'text']
		] as $output) {
			$result = (new AgentToolObservationStage())->process($this->context([
				Keys::TOOL_RESULTS => [AgentToolResult::success('c1', 'read', [], $output)]
			]));
			$this->assertSame(Keys::PHASE_OBSERVED, $result->getPatch()[Keys::PHASE]);
			$this->assertArrayNotHasKey(Keys::TERMINAL_EVIDENCE_READY, $result->getPatch());
		}
	}

	public function testFailedOrMultipleResultsCannotFinalizeEvenWithTerminalFlag(): void {
		$output = ['final_answer_ready' => true, 'answer' => 'Valid text'];
		foreach ([
			[AgentToolResult::failure('c1', 'read', [], 'error', 'Failed', [], $output)],
			[AgentToolResult::success('c1', 'read', [], $output), AgentToolResult::success('c2', 'read', [], 'other')]
		] as $results) {
			$patch = (new AgentToolObservationStage())->process($this->context([Keys::TOOL_RESULTS => $results]))->getPatch();
			$this->assertSame(Keys::PHASE_OBSERVED, $patch[Keys::PHASE]);
			$this->assertArrayNotHasKey(Keys::COMPLETED, $patch);
		}
	}

	/** @param array<string,mixed> $values */
	private function context(array $values): IAgentContext {
		$context = $this->createStub(IAgentContext::class);
		$context->method('getVar')->willReturnCallback(static fn(string $key): mixed => $values[$key] ?? null);
		return $context;
	}
}
