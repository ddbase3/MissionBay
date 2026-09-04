<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Dto\AgentProgressAssessment;
use AssistantFoundation\Dto\AgentToolResult;
use MissionBay\Context\AgentContext;
use MissionBay\Orchestrator\Service\AgentLoopProgressService;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use PHPUnit\Framework\TestCase;

final class AgentLoopProgressServiceTest extends TestCase {

	public function testExactRepeatedReadStopsImmediately(): void {
		$context = $this->context([
			$this->toolResult('call-1', ['query' => 'alpha'], ['items' => [1]], 1),
			$this->toolResult('call-2', ['query' => 'alpha'], ['items' => [1]], 2)
		], 2);

		$patch = (new AgentLoopProgressService(2))->assess($context)->getPatch();
		$assessment = $patch[AgentToolLoopContextKeys::PROGRESS_ASSESSMENTS][0];

		$this->assertInstanceOf(AgentProgressAssessment::class, $assessment);
		$this->assertTrue($patch[AgentToolLoopContextKeys::LOOP_PROGRESS_TERMINATED]);
		$this->assertSame(1, $patch[AgentToolLoopContextKeys::CONSECUTIVE_STALLED_ITERATIONS]);
		$this->assertSame('exact-call', $assessment->toArray()['metadata']['repeat_mode']);
	}

	public function testChangedArgumentsWithSameOutputGetsOneWarningBeforeStopping(): void {
		$firstContext = $this->context([
			$this->toolResult('call-1', ['query' => 'alpha'], ['items' => [1]], 1),
			$this->toolResult('call-2', ['query' => 'alpha detail'], ['items' => [1]], 2)
		], 2);

		$firstPatch = (new AgentLoopProgressService(2))->assess($firstContext)->getPatch();
		$this->assertFalse($firstPatch[AgentToolLoopContextKeys::LOOP_PROGRESS_TERMINATED]);
		$this->assertSame(1, $firstPatch[AgentToolLoopContextKeys::CONSECUTIVE_STALLED_ITERATIONS]);
		$this->assertStringContainsString('do not merely rephrase', strtolower($firstPatch[AgentToolLoopContextKeys::CONTINUATION_HINT]));

		$secondContext = $this->context([
			$this->toolResult('call-1', ['query' => 'alpha'], ['items' => [1]], 1),
			$this->toolResult('call-2', ['query' => 'alpha detail'], ['items' => [1]], 2),
			$this->toolResult('call-3', ['query' => 'alpha more detail'], ['items' => [1]], 3)
		], 3, 1);

		$secondPatch = (new AgentLoopProgressService(2))->assess($secondContext)->getPatch();
		$assessment = $secondPatch[AgentToolLoopContextKeys::PROGRESS_ASSESSMENTS][0];

		$this->assertTrue($secondPatch[AgentToolLoopContextKeys::LOOP_PROGRESS_TERMINATED]);
		$this->assertSame(2, $secondPatch[AgentToolLoopContextKeys::CONSECUTIVE_STALLED_ITERATIONS]);
		$this->assertSame('unchanged-output', $assessment->toArray()['metadata']['repeat_mode']);
	}

	public function testDifferentIdentifierWithSameOutputCountsAsProgress(): void {
		$context = $this->context([
			$this->toolResult('call-1', ['item_id' => 'item-1'], ['active' => true], 1),
			$this->toolResult('call-2', ['item_id' => 'item-2'], ['active' => true], 2)
		], 2, 1);

		$patch = (new AgentLoopProgressService(2))->assess($context)->getPatch();
		$assessment = $patch[AgentToolLoopContextKeys::PROGRESS_ASSESSMENTS][0];

		$this->assertFalse($patch[AgentToolLoopContextKeys::LOOP_PROGRESS_TERMINATED]);
		$this->assertSame(0, $patch[AgentToolLoopContextKeys::CONSECUTIVE_STALLED_ITERATIONS]);
		$this->assertSame(AgentProgressAssessment::VERDICT_PROGRESS, $assessment->getVerdict());
	}

	public function testChangedReadOutputCountsAsProgress(): void {
		$context = $this->context([
			$this->toolResult('call-1', ['query' => 'alpha'], ['items' => [1]], 1),
			$this->toolResult('call-2', ['query' => 'alpha detail'], ['items' => [1, 2]], 2)
		], 2, 1);

		$patch = (new AgentLoopProgressService(2))->assess($context)->getPatch();
		$assessment = $patch[AgentToolLoopContextKeys::PROGRESS_ASSESSMENTS][0];

		$this->assertFalse($patch[AgentToolLoopContextKeys::LOOP_PROGRESS_TERMINATED]);
		$this->assertSame(0, $patch[AgentToolLoopContextKeys::CONSECUTIVE_STALLED_ITERATIONS]);
		$this->assertSame(AgentProgressAssessment::VERDICT_PROGRESS, $assessment->getVerdict());
	}

	/** @param array<int,AgentToolResult> $observations */
	private function context(array $observations, int $iteration, int $consecutiveStalled = 0): AgentContext {
		return new AgentContext(vars: [
			AgentToolLoopContextKeys::ITERATION => $iteration,
			AgentToolLoopContextKeys::OBSERVATIONS => $observations,
			AgentToolLoopContextKeys::TOOL_DEFINITIONS => [[
				'type' => 'function',
				'annotations' => ['readOnlyHint' => true],
				'function' => [
					'name' => 'lookup',
					'parameters' => ['type' => 'object']
				]
			]],
			AgentToolLoopContextKeys::PROGRESS_ASSESSMENTS => [],
			AgentToolLoopContextKeys::CONSECUTIVE_STALLED_ITERATIONS => $consecutiveStalled,
			AgentToolLoopContextKeys::LOOP_PROGRESS_TERMINATED => false
		]);
	}

	private function toolResult(string $callId, array $arguments, array $output, int $iteration): AgentToolResult {
		return AgentToolResult::success(
			$callId,
			'lookup',
			$arguments,
			$output,
			['iteration' => $iteration]
		);
	}
}
