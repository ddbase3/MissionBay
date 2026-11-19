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

namespace Base3\MissionBay\Diagnostics;

use Base3\AssistantFoundation\Dto\AgentExecutionResult;
use Base3\AssistantFoundation\Dto\AgentExecutionStatus;
use Base3\AssistantFoundation\Dto\AgentResult;
use Base3\AssistantFoundation\Dto\AgentState;
use Base3\AssistantFoundation\Dto\AgentSuspensionState;
use Base3\CoreFoundation\Diagnostics\Api\IDiagnosticCheck;
use Base3\CoreFoundation\Diagnostics\Dto\DiagnosticCheckResult;
use Base3\MissionBay\Diagnostics\Scope\AgentExecutionDiagnosticScope;

/** Read-only contract probe for transport-neutral suspended agent results. */
final class AgentExecutionStatusDiagnosticCheck implements IDiagnosticCheck {

	public static function getName(): string {
		return 'agentexecutionstatusdiagnosticcheck';
	}

	public function getScope(): string {
		return AgentExecutionDiagnosticScope::getName();
	}

	public function run(): DiagnosticCheckResult {
		$statuses = [
			AgentExecutionStatus::AWAITING_APPROVAL,
			AgentExecutionStatus::AWAITING_INPUT
		];
		$checked = 0;

		foreach($statuses as $status) {
			$checked++;
			$state = AgentState::empty()->withSuspension(new AgentSuspensionState(true, $status));
			$agentResult = new AgentResult($status, $state);
			$envelope = new AgentExecutionResult([], [], $agentResult);

			if(!AgentExecutionStatus::isSuspended($status)
				|| !$agentResult->isSuspended()
				|| $agentResult->isCompleted()
				|| $agentResult->hasFailure()
				|| !$state->getSuspension()?->isSuspended()
				|| $state->getSuspension()->getStatus() !== $status
				|| $envelope->getAgentResult() !== $agentResult
				|| $envelope->getOutput() !== []) {
				return $this->result(DiagnosticCheckResult::FAILED, $checked);
			}
		}

		foreach([
			AgentExecutionStatus::RUNNING,
			AgentExecutionStatus::COMPLETED,
			AgentExecutionStatus::FAILED,
			AgentExecutionStatus::PARTIAL,
			AgentExecutionStatus::CANCELLED
		] as $status) {
			if(AgentExecutionStatus::isSuspended($status)) {
				return $this->result(DiagnosticCheckResult::FAILED, $checked);
			}
		}

		return $this->result(DiagnosticCheckResult::PASSED, $checked);
	}

	private function result(string $status, int $checked): DiagnosticCheckResult {
		return new DiagnosticCheckResult(
			$status,
			$status,
			['checked' => $checked],
			$status === DiagnosticCheckResult::PASSED ? '' : 'remediation',
			['checked' => $checked]
		);
	}

}
