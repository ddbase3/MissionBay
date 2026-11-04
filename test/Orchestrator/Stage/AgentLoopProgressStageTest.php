<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator\Stage;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Dto\AgentProgressAssessment;
use AssistantFoundation\Dto\AgentToolResult;
use MissionBay\Orchestrator\Stage\AgentLoopProgressStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys as Keys;
use PHPUnit\Framework\TestCase;

final class AgentLoopProgressStageTest extends TestCase {

	public function testConstructorRejectsZeroStallThreshold(): void {
		$this->expectException(\InvalidArgumentException::class);
		new AgentLoopProgressStage(maxConsecutiveStalledIterations: 0);
	}

	public function testStageIdentityAndPhaseConditions(): void {
		$stage = new AgentLoopProgressStage('progress', 'Loop progress');
		$this->assertSame('agentloopprogressstage', AgentLoopProgressStage::getName());
		$this->assertSame('progress', $stage->id());
		$this->assertSame('Loop progress', $stage->name());
		$this->assertSame(IAgentStage::AI_USAGE_NONE, $stage->getAiUsage());
		$this->assertTrue($stage->supports($this->context([Keys::PHASE => Keys::PHASE_OBSERVED])));
		$this->assertFalse($stage->supports($this->context([Keys::PHASE => Keys::PHASE_TOOLS])));
		$this->assertFalse($stage->supports($this->context([Keys::PHASE => Keys::PHASE_OBSERVED, Keys::COMPLETED => true])));
		$this->assertFalse($stage->supports($this->context([Keys::PHASE => Keys::PHASE_OBSERVED, Keys::FAILURE_CODE => 'error'])));
	}

	public function testFirstSuccessfulReadOnlyObservationCountsAsProgress(): void {
		$stage = new AgentLoopProgressStage();
		$result = $stage->process($this->context([
			Keys::ITERATION => 1,
			Keys::OBSERVATIONS => [$this->success('1', 1, ['id' => 1], ['result' => 'a'])],
			Keys::TOOL_DEFINITIONS => $this->readOnlyDefinitions()
		]));
		$assessment = $result->getPatch()[Keys::PROGRESS_ASSESSMENTS][0];
		$this->assertTrue($assessment->hasProgress());
		$this->assertSame(AgentProgressAssessment::VERDICT_PROGRESS, $assessment->getVerdict());
		$this->assertSame(0, $assessment->getConsecutiveStalledIterations());
		$this->assertFalse($result->getPatch()[Keys::LOOP_PROGRESS_TERMINATED]);
	}

	public function testIdenticalReadOnlyCallAndOutputTerminateAfterOneRepeat(): void {
		$stage = new AgentLoopProgressStage();
		$result = $stage->process($this->context([
			Keys::ITERATION => 2,
			Keys::OBSERVATIONS => [
				$this->success('earlier', 1, ['a' => 1, 'b' => 2], ['done' => true]),
				$this->success('again', 2, ['b' => 2, 'a' => 1], ['done' => true])
			],
			Keys::TOOL_DEFINITIONS => $this->readOnlyDefinitions()
		]));
		$patch = $result->getPatch();
		$this->assertTrue($patch[Keys::LOOP_PROGRESS_TERMINATED]);
		$this->assertTrue($patch[Keys::COMPLETED]);
		$this->assertSame(Keys::PHASE_FINAL, $patch[Keys::PHASE]);
		$this->assertSame(Keys::FINAL_RESPONSE_COMPLETE, $patch[Keys::FINAL_RESPONSE_MODE]);
		$this->assertSame('', $patch[Keys::CONTINUATION_HINT]);
		$this->assertStringContainsString('repeated', $patch[Keys::FINAL_RESPONSE_INSTRUCTION]);
		$this->assertSame(1, $patch[Keys::CONSECUTIVE_STALLED_ITERATIONS]);
		$this->assertTrue($patch[Keys::PROGRESS_ASSESSMENTS][0]->isStalled());
	}

	public function testConfigurableThresholdGivesContinuationBeforeTermination(): void {
		$stage = new AgentLoopProgressStage(maxConsecutiveStalledIterations: 2);
		$inputs = [
			Keys::ITERATION => 2,
			Keys::TOOL_DEFINITIONS => $this->readOnlyDefinitions(),
			Keys::OBSERVATIONS => [
				$this->success('1', 1, ['id' => 1], ['value' => 8]),
				$this->success('2', 2, ['id' => 1], ['value' => 8])
			]
		];
		$first = $stage->process($this->context($inputs))->getPatch();
		$this->assertFalse($first[Keys::LOOP_PROGRESS_TERMINATED]);
		$this->assertSame(1, $first[Keys::CONSECUTIVE_STALLED_ITERATIONS]);
		$this->assertStringContainsString('read-only', $first[Keys::CONTINUATION_HINT]);
		$second = $stage->process($this->context($inputs + [Keys::CONSECUTIVE_STALLED_ITERATIONS => 1]))->getPatch();
		$this->assertTrue($second[Keys::LOOP_PROGRESS_TERMINATED]);
		$this->assertSame(2, $second[Keys::CONSECUTIVE_STALLED_ITERATIONS]);
	}

	public function testChangedOutputAndDifferentArgumentsAreProgressNotAStall(): void {
		foreach ([
			$this->success('2', 2, ['id' => 1], ['value' => 9]),
			$this->success('2', 2, ['id' => 2], ['value' => 8])
		] as $current) {
			$patch = (new AgentLoopProgressStage())->process($this->context([
				Keys::ITERATION => 2, Keys::TOOL_DEFINITIONS => $this->readOnlyDefinitions(),
				Keys::CONSECUTIVE_STALLED_ITERATIONS => 2,
				Keys::OBSERVATIONS => [$this->success('1', 1, ['id' => 1], ['value' => 8]), $current]
			]))->getPatch();
			$this->assertTrue($patch[Keys::PROGRESS_ASSESSMENTS][0]->hasProgress());
			$this->assertFalse($patch[Keys::LOOP_PROGRESS_TERMINATED]);
			$this->assertSame(0, $patch[Keys::CONSECUTIVE_STALLED_ITERATIONS]);
		}
	}

	public function testMutationToolsAndFailedResultsCannotTriggerStall(): void {
		$conditions = [
			[[['function' => ['name' => 'read']]], $this->success('2', 2, ['id' => 1], 'same')],
			[$this->readOnlyDefinitions(), AgentToolResult::failure('2', 'read', ['id' => 1], 'denied', 'No', ['iteration' => 2])]
		];
		foreach ($conditions as [$definitions, $current]) {
			$patch = (new AgentLoopProgressStage())->process($this->context([
				Keys::ITERATION => 2, Keys::TOOL_DEFINITIONS => $definitions,
				Keys::OBSERVATIONS => [$this->success('1', 1, ['id' => 1], 'same'), $current]
			]))->getPatch();
			$this->assertSame(AgentProgressAssessment::VERDICT_UNKNOWN, $patch[Keys::PROGRESS_ASSESSMENTS][0]->getVerdict());
			$this->assertFalse($patch[Keys::LOOP_PROGRESS_TERMINATED]);
		}
	}

	public function testNoCurrentObservationsCannotBeMarkedProgressOrStalled(): void {
		$patch = (new AgentLoopProgressStage())->process($this->context([
			Keys::ITERATION => 3,
			Keys::TOOL_DEFINITIONS => $this->readOnlyDefinitions(),
			Keys::OBSERVATIONS => [$this->success('1', 1, [], 'old'), false],
			Keys::CONSECUTIVE_STALLED_ITERATIONS => 5
		]))->getPatch();
		$this->assertSame(AgentProgressAssessment::VERDICT_UNKNOWN, $patch[Keys::PROGRESS_ASSESSMENTS][0]->getVerdict());
		$this->assertSame(0, $patch[Keys::CONSECUTIVE_STALLED_ITERATIONS]);
		$this->assertFalse($patch[Keys::LOOP_PROGRESS_TERMINATED]);
	}

	public function testReadOnlyAnnotationAndTagVariantsAreRecognized(): void {
		foreach ([
			['function' => ['name' => 'read', 'read_only' => true]],
			['function' => ['name' => 'read', 'annotations' => ['readonly' => true]]],
			['function' => ['name' => 'read'], 'annotations' => ['readOnlyHint' => true]],
			['function' => ['name' => 'read'], 'tags' => 'Read-Only']
		] as $definition) {
			$patch = (new AgentLoopProgressStage())->process($this->context([
				Keys::ITERATION => 1, Keys::TOOL_DEFINITIONS => [$definition],
				Keys::OBSERVATIONS => [$this->success('c1', 1, [], ['value' => 1])]
			]))->getPatch();
			$this->assertTrue($patch[Keys::PROGRESS_ASSESSMENTS][0]->hasProgress());
		}
	}

	private function success(string $id, int $iteration, array $arguments, mixed $output): AgentToolResult {
		return AgentToolResult::success($id, 'read', $arguments, $output, ['iteration' => $iteration]);
	}

	private function readOnlyDefinitions(): array {
		return [['function' => ['name' => 'read'], 'annotations' => ['readOnlyHint' => true]]];
	}

	/** @param array<string,mixed> $values */
	private function context(array $values): IAgentContext {
		$context = $this->createStub(IAgentContext::class);
		$context->method('getVar')->willReturnCallback(static fn(string $key): mixed => $values[$key] ?? null);
		return $context;
	}
}
