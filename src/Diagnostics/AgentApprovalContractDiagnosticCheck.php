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

use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentInteractionRequest;
use Base3\AssistantFoundation\Dto\AgentInteractionResponse;
use Base3\AssistantFoundation\Dto\AgentResume;
use Base3\AssistantFoundation\Dto\AgentSuspensionScope;
use Base3\CoreFoundation\Diagnostics\Api\IDiagnosticCheck;
use Base3\CoreFoundation\Diagnostics\Dto\DiagnosticCheckResult;
use Base3\MissionBay\Diagnostics\Scope\AgentExecutionDiagnosticScope;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Throwable;

/** Read-only HITL DTO, scope, fingerprint and public-data contract check. */
final class AgentApprovalContractDiagnosticCheck implements IDiagnosticCheck {

	public static function getName(): string {
		return 'agentapprovalcontractdiagnosticcheck';
	}

	public function getScope(): string {
		return AgentExecutionDiagnosticScope::getName();
	}

	public function run(): DiagnosticCheckResult {
		$checked = 0;
		try {
			foreach (['agent', 'conversation'] as $approvalScope) {
				$checked++;
				$resume = AgentResume::fromArray([
					'resume_handle' => 'diagnostic-handle',
					'responses' => [[
						'request_id' => 'diagnostic-request',
						'decision' => AgentInteractionResponse::DECISION_APPROVE,
						'metadata' => ['approval_scope' => $approvalScope]
					]]
				]);
				$restored = AgentResume::fromArray($resume->toArray());
				if ($restored->getResumeHandle() !== 'diagnostic-handle'
					|| count($restored->getResponses()) !== 1
					|| $restored->getResponses()[0]->getMetadata()['approval_scope'] !== $approvalScope) {
					return $this->result(DiagnosticCheckResult::FAILED, $checked);
				}
			}

			$action = new AgentAction('diagnostic-action', AgentAction::TYPE_TOOL_CALL, 'diagnostic_tool', ['count' => 1]);
			$request = new AgentInteractionRequest(
				'diagnostic-request', AgentInteractionRequest::KIND_APPROVAL, $action,
				'diagnostic-fingerprint', 'Test tool', 'Approve test action', [], 'medium',
				['server_snapshot' => 'must-not-leak']
			);
			$checked++;
			$public = $request->toPublicArray();
			if (($public['metadata'] ?? null) !== [] || ($public['action']['metadata'] ?? null) !== []
				|| ($public['id'] ?? '') !== 'diagnostic-request') {
				return $this->result(DiagnosticCheckResult::FAILED, $checked);
			}

			$fingerprint = new AgentActionFingerprint();
			$definition = [
				'type' => 'function',
				'function' => ['name' => 'diagnostic_tool', 'parameters' => ['type' => 'object']],
				'annotations' => ['destructiveHint' => true],
				'approvalTargetFingerprint' => hash('sha256', 'https://diagnostic.example.invalid')
			];
			$checked++;
			$reordered = array_reverse($definition, true);
			if ($fingerprint->createToolDefinitionFingerprint($definition)
				!== $fingerprint->createToolDefinitionFingerprint($reordered)) {
				return $this->result(DiagnosticCheckResult::FAILED, $checked);
			}
			$checked++;
			$changed = $definition;
			$changed['approvalTargetFingerprint'] = hash('sha256', 'https://changed.example.invalid');
			if ($fingerprint->createToolDefinitionFingerprint($definition)
				=== $fingerprint->createToolDefinitionFingerprint($changed)) {
				return $this->result(DiagnosticCheckResult::FAILED, $checked);
			}

			$first = AgentSuspensionScope::forConversation('chatbot', 'conversation', 'user:one');
			$checked++;
			if ($first === '' || $first === AgentSuspensionScope::forConversation('chatbot', 'other', 'user:one')) {
				return $this->result(DiagnosticCheckResult::FAILED, $checked);
			}
			$checked++;
			if ($first === AgentSuspensionScope::forConversation('chatbot', 'conversation', 'user:two')) {
				return $this->result(DiagnosticCheckResult::FAILED, $checked);
			}
			$checked++;
			if (AgentSuspensionScope::forConversation('', 'conversation', 'user:one') !== '') {
				return $this->result(DiagnosticCheckResult::FAILED, $checked);
			}
		} catch (Throwable $e) {
			return $this->result(DiagnosticCheckResult::ERROR, $checked);
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
