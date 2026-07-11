<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentResultVerification;
use AssistantFoundation\Dto\AgentToolResult;
use MissionBay\Context\AgentContext;
use MissionBay\Orchestrator\Stage\AgentResultVerificationStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use PHPUnit\Framework\TestCase;

final class AgentResultVerificationStageTest extends TestCase {

	public function testMatchingActionsAndResultsAreVerified(): void {
		$context = new AgentContext(vars: [
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_AFTER_TOOLS,
			AgentToolLoopContextKeys::ITERATION => 1,
			AgentToolLoopContextKeys::ACTIONS => [
				new AgentAction('call-1', AgentAction::TYPE_TOOL_CALL, 'lookup', [], ['iteration' => 1]),
				new AgentAction('call-2', AgentAction::TYPE_TOOL_CALL, 'blocked', [], ['iteration' => 1])
			],
			AgentToolLoopContextKeys::TOOL_RESULTS => [
				AgentToolResult::success('call-1', 'lookup', [], ['ok' => true]),
				AgentToolResult::failure('call-2', 'blocked', [], 'action_denied', 'Denied by policy.')
			],
			AgentToolLoopContextKeys::RESULT_VERIFICATIONS => [],
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::FAILURE_CODE => ''
		]);
		$stage = new AgentResultVerificationStage();

		$this->assertTrue($stage->supports($context));
		$patch = $stage->process($context)->getPatch();
		$verification = $patch[AgentToolLoopContextKeys::RESULT_VERIFICATIONS][0];

		$this->assertInstanceOf(AgentResultVerification::class, $verification);
		$this->assertTrue($verification->isVerified());
		$this->assertArrayNotHasKey(AgentToolLoopContextKeys::PHASE, $patch);
		$this->assertArrayNotHasKey(AgentToolLoopContextKeys::TOOL_RESULTS, $patch);
	}

	public function testMissingActionResultFailsBeforeObservation(): void {
		$context = new AgentContext(vars: [
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_AFTER_TOOLS,
			AgentToolLoopContextKeys::ITERATION => 1,
			AgentToolLoopContextKeys::ACTIONS => [
				new AgentAction('call-1', AgentAction::TYPE_TOOL_CALL, 'lookup', [], ['iteration' => 1])
			],
			AgentToolLoopContextKeys::TOOL_RESULTS => [
				AgentToolResult::success('unexpected', 'lookup', [], ['ok' => true])
			],
			AgentToolLoopContextKeys::RESULT_VERIFICATIONS => [],
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::FAILURE_CODE => ''
		]);
		$patch = (new AgentResultVerificationStage())->process($context)->getPatch();

		$this->assertSame('tool_result_verification_failed', $patch[AgentToolLoopContextKeys::FAILURE_CODE]);
		$this->assertSame(AgentToolLoopContextKeys::PHASE_FAILED, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertFalse($patch[AgentToolLoopContextKeys::RESULT_VERIFICATIONS][0]->isVerified());
	}
}
