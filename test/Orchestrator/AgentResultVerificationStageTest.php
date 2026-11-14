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

use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentResultVerification;
use Base3\AssistantFoundation\Dto\AgentToolResult;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Orchestrator\Stage\AgentResultVerificationStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
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
