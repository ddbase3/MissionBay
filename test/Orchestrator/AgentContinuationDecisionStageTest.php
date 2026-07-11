<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Dto\AgentContinuationDecision;
use AssistantFoundation\Dto\AgentResultVerification;
use MissionBay\Context\AgentContext;
use MissionBay\Orchestrator\Stage\AgentContinuationDecisionStage;
use MissionBay\Orchestrator\Stage\AgentSemanticVerificationStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use PHPUnit\Framework\TestCase;

final class AgentContinuationDecisionStageTest extends TestCase {

	public function testVerifiedAnswerKeepsTerminalDecision(): void {
		$context = $this->createContext(new AgentResultVerification(
			iteration: 3,
			verifier: AgentSemanticVerificationStage::VERIFIER_NAME,
			verdict: AgentResultVerification::VERDICT_VERIFIED,
			summary: 'The plugin detail result answers the status question.',
			metadata: [
				'recommendation' => 'answer',
				'confidence' => 0.92
			]
		));
		$stage = new AgentContinuationDecisionStage();

		$this->assertTrue($stage->supports($context));
		$result = $stage->process($context);
		$patch = $result->getPatch();
		$decision = $patch[AgentToolLoopContextKeys::CONTINUATION_DECISIONS][0];

		$this->assertTrue($decision->shouldAnswer());
		$this->assertTrue($patch[AgentToolLoopContextKeys::COMPLETED]);
		$this->assertSame(AgentToolLoopContextKeys::PHASE_FINAL, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertSame(AgentToolLoopContextKeys::FINAL_RESPONSE_COMPLETE, $patch[AgentToolLoopContextKeys::FINAL_RESPONSE_MODE]);
	}

	public function testHighConfidenceContinueReopensToolLoop(): void {
		$context = $this->createContext(new AgentResultVerification(
			iteration: 3,
			verifier: AgentSemanticVerificationStage::VERIFIER_NAME,
			verdict: AgentResultVerification::VERDICT_FAILED,
			summary: 'Activation state is still missing.',
			issues: [[
				'code' => 'activation_missing',
				'message' => 'Activation state was not verified.',
				'detail' => []
			]],
			metadata: [
				'recommendation' => 'continue',
				'confidence' => 0.86
			]
		));
		$patch = (new AgentContinuationDecisionStage())->process($context)->getPatch();
		$decision = $patch[AgentToolLoopContextKeys::CONTINUATION_DECISIONS][0];

		$this->assertTrue($decision->shouldContinue());
		$this->assertFalse($patch[AgentToolLoopContextKeys::COMPLETED]);
		$this->assertSame(AgentToolLoopContextKeys::PHASE_MODEL, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertSame(AgentToolLoopContextKeys::FINAL_RESPONSE_NONE, $patch[AgentToolLoopContextKeys::FINAL_RESPONSE_MODE]);
		$this->assertNull($patch[AgentToolLoopContextKeys::FINAL_ASSISTANT_MESSAGE]);
		$this->assertStringContainsString('materially new evidence', $patch[AgentToolLoopContextKeys::CONTINUATION_HINT]);
	}

	public function testLowConfidenceContinueKeepsTerminalDecision(): void {
		$context = $this->createContext(new AgentResultVerification(
			iteration: 3,
			verifier: AgentSemanticVerificationStage::VERIFIER_NAME,
			verdict: AgentResultVerification::VERDICT_FAILED,
			summary: 'Another lookup might help.',
			metadata: [
				'recommendation' => 'continue',
				'confidence' => 0.45
			]
		));
		$patch = (new AgentContinuationDecisionStage())->process($context)->getPatch();
		$decision = $patch[AgentToolLoopContextKeys::CONTINUATION_DECISIONS][0];

		$this->assertTrue($decision->shouldAnswer());
		$this->assertTrue($patch[AgentToolLoopContextKeys::COMPLETED]);
		$this->assertSame(AgentToolLoopContextKeys::PHASE_FINAL, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertStringContainsString('below the configured threshold', $decision->getReason());
	}

	public function testInconclusiveVerifierKeepsTerminalDecision(): void {
		$context = $this->createContext(new AgentResultVerification(
			iteration: 3,
			verifier: AgentSemanticVerificationStage::VERIFIER_NAME,
			verdict: AgentResultVerification::VERDICT_INCONCLUSIVE,
			summary: 'Semantic verifier returned no valid structured assessment.',
			metadata: [
				'recommendation' => 'unknown',
				'confidence' => null
			]
		));
		$result = (new AgentContinuationDecisionStage())->process($context);
		$patch = $result->getPatch();
		$decision = $patch[AgentToolLoopContextKeys::CONTINUATION_DECISIONS][0];

		$this->assertTrue($decision->shouldAnswer());
		$this->assertNull($decision->getConfidence());
		$this->assertTrue($patch[AgentToolLoopContextKeys::COMPLETED]);
		$this->assertTrue($result->getMetadata()['continuation']['metadata']['primary_terminal_decision_preserved']);
	}

	public function testHighConfidenceClarifyKeepsTerminalStateWithClarificationInstruction(): void {
		$context = $this->createContext(new AgentResultVerification(
			iteration: 3,
			verifier: AgentSemanticVerificationStage::VERIFIER_NAME,
			verdict: AgentResultVerification::VERDICT_INCONCLUSIVE,
			summary: 'The requested plugin name is ambiguous.',
			metadata: [
				'recommendation' => 'clarify',
				'confidence' => 0.88
			]
		));
		$patch = (new AgentContinuationDecisionStage())->process($context)->getPatch();
		$decision = $patch[AgentToolLoopContextKeys::CONTINUATION_DECISIONS][0];

		$this->assertTrue($decision->shouldClarify());
		$this->assertTrue($patch[AgentToolLoopContextKeys::COMPLETED]);
		$this->assertStringContainsString('Ask one concise, specific clarification question', $patch[AgentToolLoopContextKeys::FINAL_RESPONSE_INSTRUCTION]);
	}

	private function createContext(AgentResultVerification $verification): AgentContext {
		return new AgentContext(vars: [
			AgentToolLoopContextKeys::ITERATION => 3,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FINAL,
			AgentToolLoopContextKeys::RESULT_VERIFICATIONS => [$verification],
			AgentToolLoopContextKeys::CONTINUATION_DECISIONS => [],
			AgentToolLoopContextKeys::FINAL_ASSISTANT_MESSAGE => ['role' => 'assistant', 'content' => 'TOOL_PHASE_COMPLETE'],
			AgentToolLoopContextKeys::FINAL_RESPONSE_MODE => AgentToolLoopContextKeys::FINAL_RESPONSE_COMPLETE,
			AgentToolLoopContextKeys::COMPLETED => true,
			AgentToolLoopContextKeys::FAILURE_CODE => ''
		]);
	}
}
