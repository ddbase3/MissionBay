<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Dto\AgentContinuationDecision;
use AssistantFoundation\Dto\AgentResultVerification;
use MissionBay\Context\AgentContext;
use MissionBay\Orchestrator\Service\AgentContinuationDecisionService;
use MissionBay\Orchestrator\Service\AgentSemanticVerificationService;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use PHPUnit\Framework\TestCase;

final class AgentContinuationDecisionServiceTest extends TestCase {

	public function testFailedVerificationContinuesEvenWithLowReportedConfidence(): void {
		$context = $this->context(new AgentResultVerification(
			iteration: 3,
			verifier: AgentSemanticVerificationService::VERIFIER_NAME,
			verdict: AgentResultVerification::VERDICT_FAILED,
			summary: 'A material evidence gap remains and an available tool can close it.',
			metadata: [
				'recommendation' => 'continue',
				'confidence' => 0.45
			]
		));

		$patch = (new AgentContinuationDecisionService())->decide($context)->getPatch();
		$decision = $patch[AgentToolLoopContextKeys::CONTINUATION_DECISIONS][0];

		$this->assertInstanceOf(AgentContinuationDecision::class, $decision);
		$this->assertTrue($decision->shouldContinue());
		$this->assertFalse($patch[AgentToolLoopContextKeys::COMPLETED]);
		$this->assertSame(AgentToolLoopContextKeys::PHASE_MODEL, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertTrue($decision->getMetadata()['continue_for_failed_verification']);
	}

	public function testInconclusiveAnswerKeepsTerminalDecision(): void {
		$context = $this->context(new AgentResultVerification(
			iteration: 3,
			verifier: AgentSemanticVerificationService::VERIFIER_NAME,
			verdict: AgentResultVerification::VERDICT_INCONCLUSIVE,
			summary: 'The remaining limitation cannot be closed by an eligible tool.',
			metadata: [
				'recommendation' => 'answer',
				'confidence' => 0.9
			]
		));

		$patch = (new AgentContinuationDecisionService())->decide($context)->getPatch();
		$decision = $patch[AgentToolLoopContextKeys::CONTINUATION_DECISIONS][0];

		$this->assertTrue($decision->shouldAnswer());
		$this->assertTrue($patch[AgentToolLoopContextKeys::COMPLETED]);
		$this->assertSame(AgentToolLoopContextKeys::PHASE_FINAL, $patch[AgentToolLoopContextKeys::PHASE]);
	}

	private function context(AgentResultVerification $verification): AgentContext {
		return new AgentContext(vars: [
			AgentToolLoopContextKeys::ITERATION => 3,
			AgentToolLoopContextKeys::RESULT_VERIFICATIONS => [$verification],
			AgentToolLoopContextKeys::CONTINUATION_DECISIONS => [],
			AgentToolLoopContextKeys::FINAL_RESPONSE_INSTRUCTION => '',
			AgentToolLoopContextKeys::LOOP_PROGRESS_TERMINATED => false,
			AgentToolLoopContextKeys::COMPLETED => true,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FINAL
		]);
	}
}
