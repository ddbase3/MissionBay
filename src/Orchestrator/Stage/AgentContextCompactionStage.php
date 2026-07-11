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
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace MissionBay\Orchestrator\Stage;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentContextCompaction;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AgentToolResult;
use Base3\Logger\Api\ILogger;
use MissionBay\Orchestrator\AgentStageResultAccumulator;
use MissionBay\Orchestrator\Service\AgentContextAssessmentService;

/**
 * AgentContextCompactionStage
 *
 * Optionally replaces oversized successful tool outputs with an AI-generated
 * factual summary before the observation stage adds them to the model message
 * stack. Failed tool results and small outputs are preserved unchanged.
 *
 * The stage is part of the default pipeline. Structural assessment always runs;
 * the additional model call is made only when a successful tool output crosses
 * the configured compaction threshold.
 */
final class AgentContextCompactionStage implements IAgentStage {

	private AgentContextAssessmentService $contextAssessmentService;

	public function __construct(
		private readonly string $id = 'context-compaction',
		private readonly string $stageName = 'context-compaction',
		private readonly int $minToolResultBytes = 12000,
		private readonly int $maxInputBytes = 80000,
		private readonly int $targetSummaryCharacters = 4000,
		?AgentContextAssessmentService $contextAssessmentService = null
	) {
		$this->contextAssessmentService = $contextAssessmentService ?? new AgentContextAssessmentService();
	}

	public static function getName(): string {
		return 'agentcontextcompactionstage';
	}

	public function id(): string {
		return $this->id;
	}

	public function name(): string {
		return $this->stageName;
	}

	public function getDescription(): string {
		return 'Uses the active chat model to summarize oversized successful tool outputs before they are added to the next model context.';
	}

	public function getAiUsage(): string {
		return IAgentStage::AI_USAGE_CONDITIONAL;
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
		$results = new AgentStageResultAccumulator($context);
		$results->apply($this->contextAssessmentService->assess($context), 'assessment');

		if ($this->hasFailure($context) || !$this->needsCompaction($context)) {
			return $results->result();
		}

		$results->apply($this->compact($context), 'compaction');

		return $results->result();
	}

	private function compact(IAgentContext $context): AgentStageResult {
		$model = $context->getVar(AgentToolLoopContextKeys::MODEL);
		$toolResults = $context->getVar(AgentToolLoopContextKeys::TOOL_RESULTS);
		$modelResults = $context->getVar(AgentToolLoopContextKeys::MODEL_RESULTS);
		$compactions = $context->getVar(AgentToolLoopContextKeys::CONTEXT_COMPACTIONS);
		$iteration = (int)($context->getVar(AgentToolLoopContextKeys::ITERATION) ?? 0);
		$logger = $context->getVar(AgentToolLoopContextKeys::LOGGER);

		if (!$model instanceof IAiChatModel) {
			return $this->failure(
				'stage_runtime_error',
				'Context compaction stage did not receive an AI chat model.',
				[]
			);
		}

		if (!is_array($toolResults)) {
			$toolResults = [];
		}

		if (!is_array($modelResults)) {
			$modelResults = [];
		}

		if (!is_array($compactions)) {
			$compactions = [];
		}

		$updatedResults = [];

		foreach ($toolResults as $toolResult) {
			if (!$toolResult instanceof AgentToolResult) {
				return $this->failure(
					'invalid_tool_result',
					'Context compaction stage received a non-normalized tool result.',
					['type' => get_debug_type($toolResult)]
				);
			}

			$originalBytes = $this->measureValue($toolResult->getOutput());
			if (!$toolResult->isSuccess() || $originalBytes < max(1, $this->minToolResultBytes)) {
				$updatedResults[] = $toolResult;
				continue;
			}

			[$serializedOutput, $inputTruncated] = $this->prepareOutput($toolResult->getOutput());

			try {
				$result = $model->complete(
					$this->buildMessages($toolResult, $serializedOutput, $inputTruncated),
					[]
				);
				$modelMetadata = $result->getMetadata()->toArray();
				$modelResults[] = $modelMetadata;
				$summary = trim($result->getContent());

				if ($summary === '') {
					throw new \RuntimeException('Compaction model returned empty content.');
				}

				$metadata = $toolResult->getMetadata();
				$metadata['compaction'] = [
					'applied' => true,
					'strategy' => 'ai-summary',
					'original_bytes' => $originalBytes,
					'compacted_bytes' => strlen($summary),
					'input_truncated' => $inputTruncated,
					'model_metadata' => $modelMetadata
				];

				$updatedResults[] = AgentToolResult::success(
					$toolResult->getCallId(),
					$toolResult->getToolName(),
					$toolResult->getArguments(),
					$summary,
					$metadata
				);
				$compactions[] = new AgentContextCompaction(
					iteration: $iteration,
					callId: $toolResult->getCallId(),
					toolName: $toolResult->getToolName(),
					applied: true,
					originalBytes: $originalBytes,
					compactedBytes: strlen($summary),
					inputTruncated: $inputTruncated,
					modelMetadata: $modelMetadata
				);
			} catch (\Throwable $e) {
				$this->logError($logger, 'Tool result compaction failed for ' . $toolResult->getToolName() . ': ' . $e->getMessage());
				$updatedResults[] = $toolResult;
				$compactions[] = new AgentContextCompaction(
					iteration: $iteration,
					callId: $toolResult->getCallId(),
					toolName: $toolResult->getToolName(),
					applied: false,
					originalBytes: $originalBytes,
					compactedBytes: $originalBytes,
					inputTruncated: $inputTruncated,
					errorMessage: $e->getMessage()
				);
			}
		}

		return AgentStageResult::patch([
			AgentToolLoopContextKeys::TOOL_RESULTS => $updatedResults,
			AgentToolLoopContextKeys::MODEL_RESULTS => $modelResults,
			AgentToolLoopContextKeys::CONTEXT_COMPACTIONS => $compactions
		]);
	}

	private function needsCompaction(IAgentContext $context): bool {
		$toolResults = $context->getVar(AgentToolLoopContextKeys::TOOL_RESULTS);

		foreach (is_array($toolResults) ? $toolResults : [] as $toolResult) {
			if (
				$toolResult instanceof AgentToolResult
				&& $toolResult->isSuccess()
				&& $this->measureValue($toolResult->getOutput()) >= max(1, $this->minToolResultBytes)
			) {
				return true;
			}
		}

		return false;
	}

	private function hasFailure(IAgentContext $context): bool {
		return trim((string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? '')) !== '';
	}

	/**
	 * @return array{0:string,1:bool}
	 */
	private function prepareOutput(mixed $output): array {
		$serialized = $this->serializeValue($output);
		$limit = max(1, $this->maxInputBytes);

		if (strlen($serialized) <= $limit) {
			return [$serialized, false];
		}

		if (function_exists('mb_strcut')) {
			$serialized = mb_strcut($serialized, 0, $limit, 'UTF-8');
		} else {
			$serialized = substr($serialized, 0, $limit);
		}

		return [$serialized, true];
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function buildMessages(AgentToolResult $toolResult, string $output, bool $inputTruncated): array {
		$arguments = json_encode(
			$toolResult->getArguments(),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		if (!is_string($arguments)) {
			$arguments = '{}';
		}

		$truncationNote = $inputTruncated
			? "\nThe supplied tool output was truncated at the configured byte limit. State this limitation in the summary."
			: '';

		return [
			[
				'role' => 'system',
				'content' => 'Compact tool output for another AI model. Preserve facts, identifiers, numbers, dates, source URLs, error conditions, uncertainty, and actionable details. Do not invent information. Return only the compacted content. Aim for at most ' . max(200, $this->targetSummaryCharacters) . ' characters.'
			],
			[
				'role' => 'user',
				'content' => "Tool: " . $toolResult->getToolName() . "\nArguments: " . $arguments . $truncationNote . "\n\nTool output:\n" . $output
			]
		];
	}

	private function serializeValue(mixed $value): string {
		if (is_string($value)) {
			return $value;
		}

		$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (is_string($json)) {
			return $json;
		}

		return (string)$value;
	}

	private function measureValue(mixed $value): int {
		return strlen($this->serializeValue($value));
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

		$logger->log('agentcontextcompactionstage', '[ERROR] ' . $message);
	}
}
