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

namespace MissionBay\Orchestrator\Stage;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AgentToolResult;

/**
 * AgentToolObservationStage
 *
 * Materializes structured AgentToolResult observations into the existing
 * MissionBay model-message context.
 *
 * Stages placed between tool-execution and tool-observation can inspect or
 * replace the structured results before the next model decision sees them.
 * The default implementation preserves the previous tool-message content
 * exactly, so existing assistant behavior remains unchanged.
 */
final class AgentToolObservationStage implements IAgentStage {

	public function __construct(
		private readonly string $id = 'tool-observation',
		private readonly string $stageName = 'tool-observation'
	) {}

	public static function getName(): string {
		return 'agenttoolobservationstage';
	}

	public function id(): string {
		return $this->id;
	}

	public function name(): string {
		return $this->stageName;
	}

	public function getDescription(): string {
		return 'Accepts the current normalized tool results as observations and materializes them as tool messages for the next model decision.';
	}

	public function getAiUsage(): string {
		return IAgentStage::AI_USAGE_NONE;
	}

	public function supports(IAgentContext $context): bool {
		$toolResults = $context->getVar(AgentToolLoopContextKeys::TOOL_RESULTS);

		return $context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_AFTER_TOOLS
			&& is_array($toolResults)
			&& $toolResults !== []
			&& $context->getVar(AgentToolLoopContextKeys::COMPLETED) !== true
			&& (string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? '') === '';
	}

	public function process(IAgentContext $context): AgentStageResult {
		$toolResults = $context->getVar(AgentToolLoopContextKeys::TOOL_RESULTS);
		$messages = $context->getVar(AgentToolLoopContextKeys::MESSAGES);
		$observations = $context->getVar(AgentToolLoopContextKeys::OBSERVATIONS);

		if (!is_array($toolResults)) {
			$toolResults = [];
		}

		if (!is_array($messages)) {
			$messages = [];
		}

		if (!is_array($observations)) {
			$observations = [];
		}

		foreach ($toolResults as $toolResult) {
			if (!$toolResult instanceof AgentToolResult) {
				return $this->failure(
					'invalid_tool_result',
					'Tool observation stage received a non-normalized tool result.',
					['type' => get_debug_type($toolResult)]
				);
			}

			$observations[] = $toolResult;
			$messages[] = [
				'role' => 'tool',
				'tool_call_id' => $toolResult->getCallId(),
				'content' => $this->encodeContent($this->getMessageContent($toolResult))
			];
		}

		return AgentStageResult::patch([
			AgentToolLoopContextKeys::MESSAGES => $messages,
			AgentToolLoopContextKeys::OBSERVATIONS => $observations,
			AgentToolLoopContextKeys::TOOL_RESULTS => [],
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_OBSERVED
		]);
	}

	private function getMessageContent(AgentToolResult $toolResult): mixed {
		$output = $toolResult->getOutput();

		if ($output !== null) {
			return $output;
		}

		if ($toolResult->isSuccess()) {
			return '';
		}

		return [
			'ok' => false,
			'error_code' => $toolResult->getErrorCode(),
			'error' => $toolResult->getErrorMessage()
		];
	}

	private function encodeContent(mixed $value): string {
		if (is_string($value)) {
			return $value;
		}

		$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			return '{}';
		}

		return $json;
	}

	/**
	 * @param array<string,mixed> $detail
	 */
	private function failure(string $code, string $message, array $detail): AgentStageResult {
		return AgentStageResult::patch([
			AgentToolLoopContextKeys::FAILURE_CODE => $code,
			AgentToolLoopContextKeys::FAILURE_MESSAGE => $message,
			AgentToolLoopContextKeys::FAILURE_DETAIL => $detail,
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FAILED
		]);
	}
}
