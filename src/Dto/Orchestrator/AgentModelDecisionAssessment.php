<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Dto\Orchestrator;

use AssistantFoundation\Dto\AiToolCall;

final class AgentModelDecisionAssessment {

	public const DECISION_TOOL_CALL = 'tool_call';
	public const DECISION_COMPLETE = 'complete';
	public const DECISION_TOOL_REQUIRED = 'tool_required';
	public const DECISION_CLARIFICATION_REQUIRED = 'clarification_required';
	public const DECISION_UNRESOLVED = 'unresolved';

	public const INTENT_MUTATION = 'mutation';
	public const INTENT_READ = 'read';
	public const INTENT_CONVERSATION = 'conversation';
	public const INTENT_UNKNOWN = 'unknown';

	/** @var array<int,string> */
	private const DECISIONS = [
		self::DECISION_TOOL_CALL,
		self::DECISION_COMPLETE,
		self::DECISION_TOOL_REQUIRED,
		self::DECISION_CLARIFICATION_REQUIRED,
		self::DECISION_UNRESOLVED
	];

	/** @var array<int,string> */
	private const INTENTS = [
		self::INTENT_MUTATION,
		self::INTENT_READ,
		self::INTENT_CONVERSATION,
		self::INTENT_UNKNOWN
	];

	/** @param array<int,string> $candidateToolNames */
	public function __construct(
		private readonly string $decision,
		private readonly string $intent,
		private readonly float $confidence,
		private readonly array $candidateToolNames = [],
		private readonly string $reason = '',
		private readonly string $clarification = '',
		private readonly bool $repairAttempted = false,
		private readonly bool $mutationIntent = false
	) {
		if (!in_array($this->decision, self::DECISIONS, true)) {
			throw new \InvalidArgumentException('Unsupported model decision: ' . $this->decision);
		}
		if (!in_array($this->intent, self::INTENTS, true)) {
			throw new \InvalidArgumentException('Unsupported model decision intent: ' . $this->intent);
		}
		if ($this->confidence < 0.0 || $this->confidence > 1.0) {
			throw new \InvalidArgumentException('Model decision confidence must be between 0 and 1.');
		}
	}

	/** @param array<int,string> $mutationToolNames */
	public static function fromControlCall(AiToolCall $call, bool $repairAttempted, array $mutationToolNames): self {
		$arguments = $call->getArguments();
		$decision = strtolower(trim((string)($arguments['decision'] ?? self::DECISION_UNRESOLVED)));
		if (!in_array($decision, self::DECISIONS, true) || $decision === self::DECISION_TOOL_CALL) {
			$decision = self::DECISION_UNRESOLVED;
		}
		$intent = strtolower(trim((string)($arguments['intent'] ?? self::INTENT_UNKNOWN)));
		if (!in_array($intent, self::INTENTS, true)) {
			$intent = self::INTENT_UNKNOWN;
		}
		$candidateToolNames = self::normalizeToolNames($arguments['candidate_tools'] ?? []);
		$mutationIntent = $intent === self::INTENT_MUTATION
			|| array_intersect($candidateToolNames, $mutationToolNames) !== [];

		return new self(
			decision: $decision,
			intent: $intent,
			confidence: max(0.0, min(1.0, (float)($arguments['confidence'] ?? 0.0))),
			candidateToolNames: $candidateToolNames,
			reason: trim((string)($arguments['reason'] ?? '')),
			clarification: trim((string)($arguments['clarification'] ?? '')),
			repairAttempted: $repairAttempted,
			mutationIntent: $mutationIntent
		);
	}

	/** @param array<int,string> $toolNames @param array<int,string> $mutationToolNames */
	public static function toolCall(array $toolNames, bool $repairAttempted, array $mutationToolNames): self {
		$toolNames = self::normalizeToolNames($toolNames);

		return new self(
			decision: self::DECISION_TOOL_CALL,
			intent: array_intersect($toolNames, $mutationToolNames) !== [] ? self::INTENT_MUTATION : self::INTENT_UNKNOWN,
			confidence: 1.0,
			candidateToolNames: $toolNames,
			reason: 'The model emitted executable tool calls.',
			repairAttempted: $repairAttempted,
			mutationIntent: array_intersect($toolNames, $mutationToolNames) !== []
		);
	}

	public static function unresolved(bool $repairAttempted, string $reason, bool $mutationIntent = false): self {
		return new self(
			decision: self::DECISION_UNRESOLVED,
			intent: self::INTENT_UNKNOWN,
			confidence: 0.0,
			candidateToolNames: [],
			reason: $reason,
			repairAttempted: $repairAttempted,
			mutationIntent: $mutationIntent
		);
	}

	public function getDecision(): string {
		return $this->decision;
	}

	public function getIntent(): string {
		return $this->intent;
	}

	public function getConfidence(): float {
		return $this->confidence;
	}

	/** @return array<int,string> */
	public function getCandidateToolNames(): array {
		return $this->candidateToolNames;
	}

	public function getReason(): string {
		return $this->reason;
	}

	public function getClarification(): string {
		return $this->clarification;
	}

	public function wasRepairAttempted(): bool {
		return $this->repairAttempted;
	}

	public function indicatesMutationIntent(): bool {
		return $this->mutationIntent;
	}

	public function isAcceptedCompletion(float $confidenceThreshold): bool {
		return $this->decision === self::DECISION_COMPLETE
			&& !$this->mutationIntent
			&& $this->confidence >= $confidenceThreshold;
	}

	public function isClarificationRequired(): bool {
		return $this->decision === self::DECISION_CLARIFICATION_REQUIRED;
	}

	/** @return array<string,mixed> */
	public function toArray(): array {
		return [
			'decision' => $this->decision,
			'intent' => $this->intent,
			'confidence' => $this->confidence,
			'candidate_tools' => $this->candidateToolNames,
			'reason' => $this->reason,
			'clarification' => $this->clarification,
			'repair_attempted' => $this->repairAttempted,
			'mutation_intent' => $this->mutationIntent
		];
	}

	/** @return array<int,string> */
	private static function normalizeToolNames(mixed $values): array {
		$result = [];
		foreach (is_array($values) ? $values : [] as $value) {
			if (!is_scalar($value)) {
				continue;
			}
			$name = trim((string)$value);
			if ($name !== '') {
				$result[$name] = $name;
			}
		}
		return array_values($result);
	}
}
