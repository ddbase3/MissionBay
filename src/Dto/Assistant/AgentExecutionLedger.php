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

namespace MissionBay\Dto\Assistant;

use AssistantFoundation\Dto\AgentToolResult;
use MissionBay\Dto\Orchestrator\AgentModelDecisionAssessment;

/**
 * Authoritative current-turn evidence used by the final response phase.
 */
final class AgentExecutionLedger {

	/**
	 * @param array<int,string> $mutationToolNames
	 * @param array<int,array<string,mixed>> $successfulMutationCalls
	 * @param array<int,array<string,mixed>> $failedMutationCalls
	 * @param array<int,array<string,mixed>> $cachedMutationCalls
	 * @param array<int,array<string,mixed>> $modelDecisionAssessments
	 * @param array<string,mixed> $latestDecisionAssessment
	 */
	private function __construct(
		private readonly array $mutationToolNames,
		private readonly array $successfulMutationCalls,
		private readonly array $failedMutationCalls,
		private readonly array $cachedMutationCalls,
		private readonly array $modelDecisionAssessments,
		private readonly array $latestDecisionAssessment,
		private readonly bool $mutationIntent
	) {}

	public static function fromTurnResult(AgentAssistantTurnResult $turnResult): self {
		$orchestrationResult = $turnResult->getOrchestrationResult();
		if ($orchestrationResult === null) {
			return new self([], [], [], [], [], [], false);
		}

		$mutationToolNames = self::normalizeNames($orchestrationResult->getMutationToolNames());
		$mutationNameMap = array_fill_keys($mutationToolNames, true);
		$successful = [];
		$failed = [];
		$cached = [];

		foreach ($orchestrationResult->getToolResults() as $toolResult) {
			if (!$toolResult instanceof AgentToolResult) {
				continue;
			}
			$toolName = trim($toolResult->getToolName());
			if ($toolName === '' || !isset($mutationNameMap[$toolName])) {
				continue;
			}
			$record = [
				'call_id' => $toolResult->getCallId(),
				'tool' => $toolName,
				'arguments' => $toolResult->getArguments(),
				'status' => $toolResult->getStatus()
			];
			if ($toolResult->isSuccess()) {
				$record['result_type'] = get_debug_type($toolResult->getOutput());
				$record['result'] = self::normalizeEvidenceValue($toolResult->getOutput());
				$metadata = $toolResult->getMetadata();
				$cache = is_array($metadata['cache'] ?? null) ? $metadata['cache'] : [];
				if (($cache['hit'] ?? false) === true) {
					$record['metadata'] = $metadata;
					$cached[] = $record;
					continue;
				}
				$successful[] = $record;
				continue;
			}
			$record['error_code'] = $toolResult->getErrorCode();
			$record['error_message'] = $toolResult->getErrorMessage();
			$record['metadata'] = $toolResult->getMetadata();
			$failed[] = $record;
		}

		$assessments = $orchestrationResult->getModelDecisionAssessments();
		$latestAssessment = [];
		$mutationIntent = $successful !== [] || $failed !== [] || $cached !== [];
		foreach ($assessments as $assessment) {
			if (!is_array($assessment)) {
				continue;
			}
			$latestAssessment = $assessment;
			$mutationIntent = $mutationIntent
				|| ($assessment['mutation_intent'] ?? false) === true
				|| strtolower(trim((string)($assessment['intent'] ?? ''))) === AgentModelDecisionAssessment::INTENT_MUTATION;
		}

		return new self(
			$mutationToolNames,
			$successful,
			$failed,
			$cached,
			array_values(array_filter($assessments, 'is_array')),
			$latestAssessment,
			$mutationIntent
		);
	}

	/** @return array<int,string> */
	public function getMutationToolNames(): array {
		return $this->mutationToolNames;
	}

	/** @return array<int,array<string,mixed>> */
	public function getSuccessfulMutationCalls(): array {
		return $this->successfulMutationCalls;
	}

	/** @return array<int,array<string,mixed>> */
	public function getFailedMutationCalls(): array {
		return $this->failedMutationCalls;
	}

	/** @return array<int,array<string,mixed>> */
	public function getCachedMutationCalls(): array {
		return $this->cachedMutationCalls;
	}

	/** @return array<int,array<string,mixed>> */
	public function getModelDecisionAssessments(): array {
		return $this->modelDecisionAssessments;
	}

	/** @return array<string,mixed> */
	public function getLatestDecisionAssessment(): array {
		return $this->latestDecisionAssessment;
	}

	public function hasMutationIntent(): bool {
		return $this->mutationIntent;
	}

	public function hasSuccessfulMutation(): bool {
		return $this->successfulMutationCalls !== [];
	}

	public function requiresFinalResponseGuard(): bool {
		return $this->mutationIntent && !$this->hasSuccessfulMutation();
	}

	public function requiresBufferedStreaming(): bool {
		return $this->requiresFinalResponseGuard();
	}

	public function getSafeFallbackResponse(): string {
		return 'Die angeforderte Änderung wurde nicht durchgeführt. In diesem Turn wurde kein entsprechender Änderungs-Tool-Aufruf erfolgreich ausgeführt.';
	}

	public function buildFinalResponseInstruction(): string {
		if ($this->mutationToolNames === [] && $this->modelDecisionAssessments === []) {
			return '';
		}

		$payload = [
			'mutation_tool_names' => $this->mutationToolNames,
			'model_decision_assessments' => $this->modelDecisionAssessments,
			'latest_model_decision' => $this->latestDecisionAssessment,
			'mutation_intent' => $this->mutationIntent,
			'successful_mutation_calls' => $this->successfulMutationCalls,
			'failed_mutation_calls' => $this->failedMutationCalls,
			'cached_mutation_calls' => $this->cachedMutationCalls,
			'authoritative_conclusion' => $this->hasSuccessfulMutation()
				? 'At least one mutation tool completed successfully in this turn.'
				: 'No mutation tool completed successfully in this turn.'
		];
		$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
		if (!is_string($json)) {
			$json = '{}';
		}

		return implode("\n", [
			'Authoritative current-turn execution ledger:',
			$json,
			'Use this ledger as the only source of truth for claims about state-changing actions.',
			'Approval, intent, previous conversation history, a proposed action, or a cache hit is not proof of execution.',
			'Never state or imply that a mutation succeeded unless successful_mutation_calls contains the corresponding tool call.',
			'When mutation_intent is true and successful_mutation_calls is empty, state that the requested change was not performed, or ask the required clarification.'
		]);
	}

	private static function normalizeEvidenceValue(mixed $value, int $depth = 0): mixed {
		if ($depth >= 8) {
			return ['type' => get_debug_type($value), 'truncated' => true];
		}

		if ($value === null || is_scalar($value)) {
			return $value;
		}

		if (is_array($value)) {
			$result = [];
			foreach ($value as $key => $item) {
				$result[$key] = self::normalizeEvidenceValue($item, $depth + 1);
			}
			return $result;
		}

		if ($value instanceof \JsonSerializable) {
			return self::normalizeEvidenceValue($value->jsonSerialize(), $depth + 1);
		}

		if ($value instanceof \Stringable) {
			return (string)$value;
		}

		return ['type' => get_debug_type($value)];
	}

	/** @param array<int,mixed> $names @return array<int,string> */
	private static function normalizeNames(array $names): array {
		$result = [];
		foreach ($names as $name) {
			if (!is_scalar($name)) {
				continue;
			}
			$name = trim((string)$name);
			if ($name !== '') {
				$result[$name] = $name;
			}
		}
		return array_values($result);
	}
}
