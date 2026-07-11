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

/**
 * Finalizes the visible assistant output after the model has stopped
 * requesting tools.
 *
 * The default mode reuses the terminal provider-neutral assistant content
 * produced by model-decision. This avoids a hidden duplicate model call and
 * guarantees that the answer which ended the tool loop is actually emitted.
 *
 * An explicitly configured regenerate mode can perform a separate final
 * model call without tools. It remains optional because it adds latency,
 * provider usage, and another possible failure boundary.
 */
final class AgentFinalAnswerStage implements IAgentStage {

	public const MODE_REUSE_TERMINAL = 'reuse-terminal';
	public const MODE_REGENERATE = 'regenerate';

	public function __construct(
		private readonly string $id = 'final-answer',
		private readonly string $stageName = 'final-answer',
		private readonly string $mode = self::MODE_REUSE_TERMINAL
	) {
		if (!in_array($this->mode, [self::MODE_REUSE_TERMINAL, self::MODE_REGENERATE], true)) {
			throw new \InvalidArgumentException('Unsupported final answer mode: ' . $this->mode);
		}
	}

	public static function getName(): string {
		return 'agentfinalanswerstage';
	}

	public function id(): string {
		return $this->id;
	}

	public function name(): string {
		return $this->stageName;
	}

	public function getDescription(): string {
		if ($this->mode === self::MODE_REGENERATE) {
			return 'Generates and emits a separate final response without tools after the tool loop has ended.';
		}

		return 'Publishes the terminal assistant content that ended the tool loop without making another model call.';
	}

	public function getAiUsage(): string {
		return $this->mode === self::MODE_REGENERATE
			? IAgentStage::AI_USAGE_REQUIRED
			: IAgentStage::AI_USAGE_NONE;
	}

	public function supports(IAgentContext $context): bool {
		return $context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_FINAL
			&& (string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? '') === '';
	}

	public function process(IAgentContext $context): AgentStageResult {
		$terminalContent = $this->readTerminalContent(
			$context->getVar(AgentToolLoopContextKeys::FINAL_ASSISTANT_MESSAGE)
		);

		if ($this->mode === self::MODE_REUSE_TERMINAL) {
			if ($terminalContent === '') {
				return $this->failure(
					'final_answer_missing',
					'The model ended the tool phase without a usable assistant response.',
					[]
				);
			}

			$this->emitContent($context, $terminalContent);

			return $this->complete($terminalContent, [
				'mode' => self::MODE_REUSE_TERMINAL,
				'source' => 'model-decision',
				'characters' => strlen($terminalContent)
			]);
		}

		return $this->regenerate($context, $terminalContent);
	}

	private function regenerate(IAgentContext $context, string $terminalContent): AgentStageResult {
		$model = $context->getVar(AgentToolLoopContextKeys::MODEL);
		$messages = $context->getVar(AgentToolLoopContextKeys::MESSAGES);
		$modelResults = $context->getVar(AgentToolLoopContextKeys::MODEL_RESULTS);
		$eventCallback = $context->getVar(AgentToolLoopContextKeys::EVENT_CALLBACK);
		$logger = $context->getVar(AgentToolLoopContextKeys::LOGGER);

		if (!$model instanceof IAiChatModel) {
			return $this->fallbackOrFailure(
				$context,
				$terminalContent,
				'final_answer_model_missing',
				'Final answer regeneration did not receive an AI chat model.',
				[]
			);
		}

		if (!is_array($messages)) {
			$messages = [];
		}

		if (!is_array($modelResults)) {
			$modelResults = [];
		}

		$streamedContent = '';

		try {
			if (is_callable($eventCallback)) {
				$result = $model->streamResult(
					$messages,
					[],
					function(string $delta) use (&$streamedContent, $eventCallback): void {
						$streamedContent .= $delta;
						$this->emit($eventCallback, 'token', ['text' => $delta]);
					},
					function(array $meta) use ($eventCallback): void {
						$this->emit($eventCallback, 'meta', $meta);
					}
				);
			} else {
				$result = $model->complete($messages, []);
			}
		} catch (\Throwable $e) {
			$this->logError($logger, 'Final answer regeneration failed: ' . $e->getMessage());

			if ($streamedContent !== '') {
				return $this->complete($streamedContent, [
					'mode' => self::MODE_REGENERATE,
					'source' => 'partial-stream',
					'characters' => strlen($streamedContent),
					'warning' => 'stream_interrupted',
					'error' => $e->getMessage()
				]);
			}

			return $this->fallbackOrFailure(
				$context,
				$terminalContent,
				'final_answer_generation_failed',
				'Final answer regeneration failed.',
				[
					'type' => get_class($e),
					'message' => $e->getMessage(),
					'code' => $e->getCode()
				]
			);
		}

		$modelResults[] = $result->getMetadata()->toArray();
		$content = $result->getContent();
		if ($content === '' && $streamedContent !== '') {
			$content = $streamedContent;
		}

		if ($content === '') {
			return $this->fallbackOrFailure(
				$context,
				$terminalContent,
				'final_answer_empty',
				'Final answer regeneration returned no content.',
				[],
				$modelResults
			);
		}

		if (is_callable($eventCallback) && $streamedContent === '') {
			$this->emit($eventCallback, 'token', ['text' => $content]);
		}

		return AgentStageResult::patch([
			AgentToolLoopContextKeys::MODEL_RESULTS => $modelResults,
			AgentToolLoopContextKeys::FINAL_OUTPUT_CONTENT => $content,
			AgentToolLoopContextKeys::COMPLETED => true,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_COMPLETE
		], [
			'output' => [
				'mode' => self::MODE_REGENERATE,
				'source' => 'final-model-call',
				'characters' => strlen($content)
			]
		]);
	}

	/**
	 * @param array<int,array<string,mixed>> $modelResults
	 */
	private function fallbackOrFailure(
		IAgentContext $context,
		string $terminalContent,
		string $failureCode,
		string $failureMessage,
		array $failureDetail,
		array $modelResults = []
	): AgentStageResult {
		if ($terminalContent === '') {
			return $this->failure($failureCode, $failureMessage, $failureDetail);
		}

		$this->emitContent($context, $terminalContent);
		$patch = [
			AgentToolLoopContextKeys::FINAL_OUTPUT_CONTENT => $terminalContent,
			AgentToolLoopContextKeys::COMPLETED => true,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_COMPLETE
		];
		if ($modelResults !== []) {
			$patch[AgentToolLoopContextKeys::MODEL_RESULTS] = $modelResults;
		}

		return AgentStageResult::patch($patch, [
			'output' => [
				'mode' => self::MODE_REGENERATE,
				'source' => 'model-decision-fallback',
				'characters' => strlen($terminalContent),
				'warning' => $failureCode,
				'detail' => $failureDetail
			]
		]);
	}

	/**
	 * @param array<string,mixed> $metadata
	 */
	private function complete(string $content, array $metadata): AgentStageResult {
		return AgentStageResult::patch([
			AgentToolLoopContextKeys::FINAL_OUTPUT_CONTENT => $content,
			AgentToolLoopContextKeys::COMPLETED => true,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_COMPLETE
		], [
			'output' => $metadata
		]);
	}

	private function emitContent(IAgentContext $context, string $content): void {
		$eventCallback = $context->getVar(AgentToolLoopContextKeys::EVENT_CALLBACK);
		if (!is_callable($eventCallback)) {
			return;
		}

		$this->emit($eventCallback, 'token', ['text' => $content]);
	}

	private function emit(callable $eventCallback, string $event, array $payload): void {
		try {
			$eventCallback($event, $payload);
		} catch (\Throwable $e) {
			// Output transport failures must not alter the final answer state.
		}
	}

	private function readTerminalContent(mixed $message): string {
		if (!is_array($message)) {
			return '';
		}

		$content = $message['content'] ?? '';
		return is_scalar($content) ? trim((string)$content) : '';
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

	private function logError(mixed $logger, string $message): void {
		if (!$logger instanceof ILogger) {
			return;
		}

		$logger->log('agentfinalanswerstage', '[ERROR] ' . $message);
	}
}
