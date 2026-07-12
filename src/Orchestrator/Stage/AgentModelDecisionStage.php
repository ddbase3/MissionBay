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
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentStageResult;
use Base3\Logger\Api\ILogger;
use MissionBay\Ai\AgentChatMessageAdapter;

/**
 * AgentModelDecisionStage
 *
 * Executes one model decision for the current tool-loop iteration.
 *
 * A terminal assistant message completes the current tool phase. An
 * assistant message containing tool calls is appended to the working
 * message stack and handed to the following tool execution stage.
 */
final class AgentModelDecisionStage implements IAgentStage {

	private const TERMINAL_SIGNAL = 'TOOL_PHASE_COMPLETE';

	public function __construct(
		private readonly string $id = 'model-decision',
		private readonly string $stageName = 'model-decision'
	) {}

	public static function getName(): string {
		return 'agentmodeldecisionstage';
	}

	public function id(): string {
		return $this->id;
	}

	public function name(): string {
		return $this->stageName;
	}

	public function getDescription(): string {
		return 'Calls the active chat model once to request tools or return a short tool-phase completion signal.';
	}

	public function getAiUsage(): string {
		return IAgentStage::AI_USAGE_REQUIRED;
	}

	public function supports(IAgentContext $context): bool {
		return $context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_MODEL
			&& $context->getVar(AgentToolLoopContextKeys::COMPLETED) !== true
			&& (string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? '') === '';
	}

	public function process(IAgentContext $context): AgentStageResult {
		$model = $context->getVar(AgentToolLoopContextKeys::MODEL);
		$messages = $context->getVar(AgentToolLoopContextKeys::MESSAGES);
		$toolDefinitions = $context->getVar(AgentToolLoopContextKeys::TOOL_DEFINITIONS);
		$iteration = (int)($context->getVar(AgentToolLoopContextKeys::ITERATION) ?? 0);
		$logger = $context->getVar(AgentToolLoopContextKeys::LOGGER);
		$continuationHint = $context->getVar(AgentToolLoopContextKeys::CONTINUATION_HINT);

		if (!$model instanceof IAiChatModel) {
			return $this->failure(
				'stage_runtime_error',
				'Model decision stage did not receive an AI chat model.',
				[]
			);
		}

		if (!is_array($messages)) {
			$messages = [];
		}

		if (!is_array($toolDefinitions)) {
			$toolDefinitions = [];
		}

		$this->log($logger, 'Tool phase iteration ' . $iteration . ' started.');

		try {
			$result = $model->complete(
				$this->buildDecisionMessages(
					$messages,
					is_scalar($continuationHint) ? trim((string)$continuationHint) : ''
				),
				$toolDefinitions
			);
		} catch (\Throwable $e) {
			$this->logError($logger, 'Model completion call failed: ' . $e->getMessage());
			$observations = $context->getVar(AgentToolLoopContextKeys::OBSERVATIONS);

			if (is_array($observations) && $observations !== []) {
				return AgentStageResult::patch([
					AgentToolLoopContextKeys::FINAL_RESPONSE_MODE => AgentToolLoopContextKeys::FINAL_RESPONSE_PARTIAL,
					AgentToolLoopContextKeys::TERMINAL_EVIDENCE_READY => true,
					AgentToolLoopContextKeys::FINAL_RESPONSE_INSTRUCTION => implode("\n", [
						'The next tool-decision call failed after successful tool observations were already collected.',
						'Produce the most useful direct answer from the available observations.',
						'State uncertainty where evidence is incomplete. Do not expose internal timeout or orchestration details.'
					]),
					AgentToolLoopContextKeys::PENDING_TOOL_CALLS => [],
					AgentToolLoopContextKeys::COMPLETED => true,
					AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FINAL
				], [
					'recovered_from_model_error' => true,
					'error_type' => get_class($e),
					'error_message' => $e->getMessage(),
					'error_code' => $e->getCode()
				]);
			}

			return $this->failure(
				'model_raw_error',
				'Model call failed during tool orchestration.',
				[
					'type' => get_class($e),
					'message' => $e->getMessage(),
					'code' => $e->getCode()
				]
			);
		}

		$assistant = AgentChatMessageAdapter::assistantMessage($result);
		$toolCalls = $result->getToolCalls();
		$modelResults = $context->getVar(AgentToolLoopContextKeys::MODEL_RESULTS);
		if (!is_array($modelResults)) {
			$modelResults = [];
		}
		$modelResults[] = $result->getMetadata()->toArray();

		if ($toolCalls === []) {
			$this->log($logger, 'Tool phase completed after ' . $iteration . ' iteration(s). Final answer phase starts.');

			return AgentStageResult::patch([
				AgentToolLoopContextKeys::FINAL_ASSISTANT_MESSAGE => $assistant,
				AgentToolLoopContextKeys::FINAL_RESPONSE_MODE => AgentToolLoopContextKeys::FINAL_RESPONSE_COMPLETE,
				AgentToolLoopContextKeys::MODEL_RESULTS => $modelResults,
				AgentToolLoopContextKeys::CONTINUATION_HINT => '',
				AgentToolLoopContextKeys::PENDING_TOOL_CALLS => [],
				AgentToolLoopContextKeys::COMPLETED => true,
				AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FINAL
			]);
		}

		$messages[] = $assistant;

		return AgentStageResult::patch([
			AgentToolLoopContextKeys::MESSAGES => $messages,
			AgentToolLoopContextKeys::MODEL_RESULTS => $modelResults,
			AgentToolLoopContextKeys::CONTINUATION_HINT => '',
			AgentToolLoopContextKeys::PENDING_TOOL_CALLS => $toolCalls,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_TOOLS
		]);
	}

	/**
	 * Adds a control-only instruction to the current model request without
	 * persisting it in the working message stack. The dedicated final response
	 * call therefore receives the original system prompt and observations.
	 *
	 * @param array<int,array<string,mixed>> $messages
	 * @return array<int,array<string,mixed>>
	 */
	private function buildDecisionMessages(array $messages, string $continuationHint = ''): array {
		$instruction = 'You are in the tool-decision phase. Request additional tools only when they are expected to add materially new evidence. When no further tool call is required, do not write the user-facing answer. Return exactly ' . self::TERMINAL_SIGNAL . ' and nothing else. The final answer is generated in a separate response phase.';

		if ($continuationHint !== '') {
			$instruction .= "\n\n" . $continuationHint;
		}
		$result = $messages;

		foreach ($result as $index => $message) {
			if (
				!is_array($message) ||
				($message['role'] ?? null) !== 'system' ||
				!is_scalar($message['content'] ?? null)
			) {
				continue;
			}

			$content = trim((string)$message['content']);
			$result[$index]['content'] = $content === ''
				? $instruction
				: $content . "\n\n" . $instruction;

			return $result;
		}

		array_unshift($result, [
			'role' => 'system',
			'content' => $instruction
		]);

		return $result;
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

	private function log(mixed $logger, string $message): void {
		if (!$logger instanceof ILogger) {
			return;
		}

		$logger->log('agenttoolorchestrator', $message);
	}

	private function logError(mixed $logger, string $message): void {
		$this->log($logger, '[ERROR] ' . $message);
	}
}
