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

namespace Base3\MissionBay\Test\Diagnostics;

use Base3\AssistantFoundation\Dto\AgentExecutionResult;
use Base3\AssistantFoundation\Dto\AgentExecutionStatus;
use Base3\AssistantFoundation\Dto\AgentResult;
use Base3\AssistantFoundation\Dto\AgentState;
use Base3\AssistantFoundation\Dto\AgentSuspensionState;
use Base3\CoreFoundation\Diagnostics\Dto\DiagnosticCheckResult;
use Base3\MissionBay\Diagnostics\AgentExecutionStatusDiagnosticCheck;
use Base3\MissionBay\Diagnostics\Scope\AgentExecutionDiagnosticScope;
use PHPUnit\Framework\TestCase;

final class AgentExecutionStatusDiagnosticCheckTest extends TestCase {

	public function testNewAgentScopeUsesTheEstablishedDiagnosticDisplay(): void {
		$scope = new AgentExecutionDiagnosticScope();
		$this->assertSame('agentexecutiondiagnosticscope', AgentExecutionDiagnosticScope::getName());
		$this->assertSame('diagnosticscopedisplay', $scope->getDisplayName());
		$this->assertSame(35, $scope->getPriority());
	}

	public function testReadOnlyStatusCheckRequiresNoAgentOrToolExecution(): void {
		$check = new AgentExecutionStatusDiagnosticCheck();
		$this->assertSame('agentexecutionstatusdiagnosticcheck', AgentExecutionStatusDiagnosticCheck::getName());
		$this->assertSame(AgentExecutionDiagnosticScope::getName(), $check->getScope());

		foreach(range(1, 2) as $iteration) {
			$result = $check->run();
			$this->assertSame(DiagnosticCheckResult::PASSED, $result->status);
			$this->assertSame('passed', $result->messageKey);
			$this->assertSame(['checked' => 2], $result->details);
			$this->assertSame('', $result->remediationKey);
		}
	}

	public function testSuspendedExecutionEnvelopeNeedsNoAssistantMessage(): void {
		foreach([AgentExecutionStatus::AWAITING_APPROVAL, AgentExecutionStatus::AWAITING_INPUT] as $status) {
			$state = AgentState::empty()->withSuspension(new AgentSuspensionState(true, $status));
			$agentResult = new AgentResult($status, $state, ['content' => '']);
			$envelope = new AgentExecutionResult(['content' => ''], [], $agentResult);

			$this->assertTrue($envelope->getAgentResult()->isSuspended());
			$this->assertFalse($envelope->getAgentResult()->hasFailure());
			$this->assertFalse($envelope->getAgentResult()->isCompleted());
			$this->assertSame($status, $envelope->getAgentResult()->getState()->getSuspension()->getStatus());
		}
	}

}
