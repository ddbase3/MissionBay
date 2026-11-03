<?php declare(strict_types=1);

namespace MissionBay\Test\Dto\Orchestrator;

use AssistantFoundation\Dto\AiToolCall;
use InvalidArgumentException;
use MissionBay\Dto\Orchestrator\AgentModelDecisionAssessment;
use PHPUnit\Framework\TestCase;

final class AgentModelDecisionAssessmentTest extends TestCase {

	public function testCompletionMustBeNonMutationAndMeetConfidenceThreshold(): void {
		$assessment = new AgentModelDecisionAssessment('complete', 'read', 0.75);
		$this->assertTrue($assessment->isAcceptedCompletion(0.7));
		$this->assertFalse($assessment->isAcceptedCompletion(0.8));
		$this->assertFalse((new AgentModelDecisionAssessment('complete', 'mutation', 0.99, mutationIntent: true))->isAcceptedCompletion(0.7));
		$this->assertFalse((new AgentModelDecisionAssessment('tool_required', 'read', 1.0))->isAcceptedCompletion(0.7));
	}

	public function testControlCallNormalizesNamesAndDetectsMutationTools(): void {
		$call = new AiToolCall('control', 'missionbay_tool_phase_decision', [
			'decision' => ' COMPLETE ', 'intent' => ' READ ', 'confidence' => 1.5,
			'candidate_tools' => [' read ', 'mutate', 'mutate', [], null],
			'missing_arguments' => [' target ', '', 'target'], 'reason' => ' reason '
		]);
		$assessment = AgentModelDecisionAssessment::fromControlCall($call, true, ['mutate']);
		$this->assertSame('complete', $assessment->getDecision());
		$this->assertSame('read', $assessment->getIntent());
		$this->assertSame(1.0, $assessment->getConfidence());
		$this->assertSame(['read', 'mutate'], $assessment->getCandidateToolNames());
		$this->assertSame(['target'], $assessment->getMissingArgumentNames());
		$this->assertTrue($assessment->indicatesMutationIntent());
		$this->assertTrue($assessment->wasRepairAttempted());
		$this->assertFalse($assessment->isAcceptedCompletion(0.7));
		$this->assertSame('reason', $assessment->getReason());
	}

	public function testInvalidControlDecisionFallsBackToUnresolved(): void {
		$assessment = AgentModelDecisionAssessment::fromControlCall(new AiToolCall('c', 'decision', [
			'decision' => 'tool_call', 'intent' => 'unknown-intent', 'confidence' => -0.2
		]), false, []);
		$this->assertSame('unresolved', $assessment->getDecision());
		$this->assertSame('unknown', $assessment->getIntent());
		$this->assertSame(0.0, $assessment->getConfidence());
	}

	public function testToolCallAndUnresolvedFactoriesPreserveIntent(): void {
		$assessment = AgentModelDecisionAssessment::toolCall([' mutation ', 'mutation', 'lookup'], true, ['mutation']);
		$this->assertSame('tool_call', $assessment->getDecision());
		$this->assertSame('mutation', $assessment->getIntent());
		$this->assertTrue($assessment->indicatesMutationIntent());
		$this->assertSame(['mutation', 'lookup'], $assessment->toArray()['candidate_tools']);
		$unresolved = AgentModelDecisionAssessment::unresolved(false, 'cannot decide', true);
		$this->assertSame('cannot decide', $unresolved->getReason());
		$this->assertTrue($unresolved->indicatesMutationIntent());
		$this->assertFalse($unresolved->isClarificationRequired());
		$this->assertTrue((new AgentModelDecisionAssessment('clarification_required', 'read', 0.5))->isClarificationRequired());
	}

	public function testConstructorRejectsInvalidDecisionsIntentsAndConfidence(): void {
		foreach ([['bad', 'read', 0.5], ['complete', 'bad', 0.5], ['complete', 'read', -0.1], ['complete', 'read', 1.1]] as [$decision, $intent, $confidence]) {
			try {
				new AgentModelDecisionAssessment($decision, $intent, $confidence);
				$this->fail('Invalid model decision assessment was accepted.');
			} catch (InvalidArgumentException) {
				$this->addToAssertionCount(1);
			}
		}
	}
}
