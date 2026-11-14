<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace Base3\MissionBay\Test\Orchestrator;

use Base3\AssistantFoundation\Dto\AgentContinuationDecision;
use Base3\AssistantFoundation\Dto\AgentResultVerification;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Orchestrator\Service\AgentContinuationDecisionService;
use Base3\MissionBay\Orchestrator\Service\AgentSemanticVerificationService;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
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
