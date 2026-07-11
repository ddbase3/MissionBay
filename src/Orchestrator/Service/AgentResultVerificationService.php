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

namespace MissionBay\Orchestrator\Service;

use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentResultVerification;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AgentToolResult;

/**
 * AgentResultVerificationService
 *
 * Performs deterministic structural verification of the final normalized tool
 * result set before the observation stage commits those results to the model
 * message stack.
 *
 * This stage does not judge whether the information answers the user's task.
 * Semantic sufficiency and contradiction detection belong to a later optional
 * AI verification stage. The responsibility here is narrower and mandatory:
 * malformed, duplicated, missing, or uncorrelated tool results must not enter
 * the next model context unnoticed.
 */
final class AgentResultVerificationService {

	public const VERIFIER = 'structural-tool-result-contract';

	public function verify(IAgentContext $context): AgentStageResult {
		$toolResults = $context->getVar(AgentToolLoopContextKeys::TOOL_RESULTS);
		$actions = $context->getVar(AgentToolLoopContextKeys::ACTIONS);
		$verifications = $context->getVar(AgentToolLoopContextKeys::RESULT_VERIFICATIONS);
		$iteration = (int)($context->getVar(AgentToolLoopContextKeys::ITERATION) ?? 0);

		if (!is_array($toolResults)) {
			$toolResults = [];
		}

		if (!is_array($actions)) {
			$actions = [];
		}

		if (!is_array($verifications)) {
			$verifications = [];
		}

		$issues = [];
		$resultIds = [];

		foreach ($toolResults as $index => $toolResult) {
			if (!$toolResult instanceof AgentToolResult) {
				$issues[] = $this->issue(
					'invalid_tool_result_type',
					'Tool result is not an AgentToolResult instance.',
					['index' => $index, 'type' => get_debug_type($toolResult)]
				);
				continue;
			}

			$callId = trim($toolResult->getCallId());
			$toolName = trim($toolResult->getToolName());

			if ($callId === '') {
				$issues[] = $this->issue(
					'missing_call_id',
					'Tool result has no call id.',
					['index' => $index, 'tool' => $toolName]
				);
			} elseif (isset($resultIds[$callId])) {
				$issues[] = $this->issue(
					'duplicate_call_id',
					'Multiple tool results use the same call id.',
					['index' => $index, 'call_id' => $callId]
				);
			} else {
				$resultIds[$callId] = true;
			}

			if ($toolName === '') {
				$issues[] = $this->issue(
					'missing_tool_name',
					'Tool result has no tool name.',
					['index' => $index, 'call_id' => $callId]
				);
			}

			if (!in_array($toolResult->getStatus(), [
				AgentToolResult::STATUS_SUCCESS,
				AgentToolResult::STATUS_FAILURE
			], true)) {
				$issues[] = $this->issue(
					'invalid_result_status',
					'Tool result uses an unsupported status.',
					[
						'index' => $index,
						'call_id' => $callId,
						'status' => $toolResult->getStatus()
					]
				);
			}

			if (!$toolResult->isSuccess()) {
				if (trim($toolResult->getErrorCode()) === '') {
					$issues[] = $this->issue(
						'missing_error_code',
						'Failed tool result has no error code.',
						['index' => $index, 'call_id' => $callId, 'tool' => $toolName]
					);
				}

				if (trim($toolResult->getErrorMessage()) === '') {
					$issues[] = $this->issue(
						'missing_error_message',
						'Failed tool result has no error message.',
						['index' => $index, 'call_id' => $callId, 'tool' => $toolName]
					);
				}
			}
		}

		$currentActions = $this->getCurrentActions($actions, $iteration);
		$actionCorrelationChecked = $currentActions !== [];

		if ($actionCorrelationChecked) {
			$actionIds = [];

			foreach ($currentActions as $index => $action) {
				$actionId = trim($action->getId());

				if ($actionId === '') {
					$issues[] = $this->issue(
						'missing_action_id',
						'Current action has no id.',
						['index' => $index]
					);
					continue;
				}

				if (isset($actionIds[$actionId])) {
					$issues[] = $this->issue(
						'duplicate_action_id',
						'Multiple current actions use the same id.',
						['index' => $index, 'action_id' => $actionId]
					);
					continue;
				}

				$actionIds[$actionId] = true;
			}

			foreach (array_keys($actionIds) as $actionId) {
				if (!isset($resultIds[$actionId])) {
					$issues[] = $this->issue(
						'missing_action_result',
						'Current action has no corresponding tool result.',
						['action_id' => $actionId]
					);
				}
			}

			foreach (array_keys($resultIds) as $callId) {
				if (!isset($actionIds[$callId])) {
					$issues[] = $this->issue(
						'unexpected_tool_result',
						'Tool result has no corresponding current action.',
						['call_id' => $callId]
					);
				}
			}
		}

		$verification = new AgentResultVerification(
			iteration: $iteration,
			verifier: self::VERIFIER,
			verdict: $issues === []
				? AgentResultVerification::VERDICT_VERIFIED
				: AgentResultVerification::VERDICT_FAILED,
			summary: $issues === []
				? 'Tool results satisfy the structural result contract.'
				: 'Tool results failed structural verification.',
			issues: $issues,
			metadata: [
				'tool_result_count' => count($toolResults),
				'current_action_count' => count($currentActions),
				'action_correlation_checked' => $actionCorrelationChecked,
				'phase' => AgentToolLoopContextKeys::PHASE_AFTER_TOOLS
			]
		);
		$verifications[] = $verification;

		if ($issues !== []) {
			return AgentStageResult::patch([
				AgentToolLoopContextKeys::RESULT_VERIFICATIONS => $verifications,
				AgentToolLoopContextKeys::FAILURE_CODE => 'tool_result_verification_failed',
				AgentToolLoopContextKeys::FAILURE_MESSAGE => 'Tool results failed structural verification before observation.',
				AgentToolLoopContextKeys::FAILURE_DETAIL => [
					'verification' => $verification->toArray()
				],
				AgentToolLoopContextKeys::COMPLETED => false,
				AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FAILED
			]);
		}

		return AgentStageResult::patch([
			AgentToolLoopContextKeys::RESULT_VERIFICATIONS => $verifications
		]);
	}

	/**
	 * @param array<int,mixed> $actions
	 * @return array<int,AgentAction>
	 */
	private function getCurrentActions(array $actions, int $iteration): array {
		$current = [];

		foreach ($actions as $action) {
			if (!$action instanceof AgentAction) {
				continue;
			}

			$metadata = $action->getMetadata();
			if ((int)($metadata['iteration'] ?? 0) !== $iteration) {
				continue;
			}

			$current[] = $action;
		}

		return $current;
	}

	/**
	 * @param array<string,mixed> $detail
	 * @return array<string,mixed>
	 */
	private function issue(string $code, string $message, array $detail = []): array {
		return [
			'code' => $code,
			'message' => $message,
			'detail' => $detail
		];
	}
}
