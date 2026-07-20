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

namespace MissionBay\Dto\Orchestrator;

final class AgentModelDecisionConfig {

	public const STRATEGY_SIMPLE = 'simple-model-decision';
	public const STRATEGY_AI_GUARDED = 'ai-guarded-model-decision';

	/** @var array<int,string> */
	private const STRATEGIES = [
		self::STRATEGY_SIMPLE,
		self::STRATEGY_AI_GUARDED
	];

	public function __construct(
		private readonly string $strategy = self::STRATEGY_AI_GUARDED,
		private readonly bool $repairEnabled = true,
		private readonly float $confidenceThreshold = 0.7
	) {
		if (!in_array($this->strategy, self::STRATEGIES, true)) {
			throw new \InvalidArgumentException('Unsupported model decision strategy: ' . $this->strategy);
		}
		if ($this->confidenceThreshold < 0.0 || $this->confidenceThreshold > 1.0) {
			throw new \InvalidArgumentException('Model decision confidence threshold must be between 0 and 1.');
		}
	}

	public static function simple(): self {
		return new self(self::STRATEGY_SIMPLE, false, 0.7);
	}

	public static function aiGuarded(): self {
		return new self(self::STRATEGY_AI_GUARDED, true, 0.7);
	}

	/** @param array<string,mixed> $data */
	public static function fromArray(array $data): self {
		$strategy = strtolower(trim((string)($data['strategy'] ?? self::STRATEGY_AI_GUARDED)));
		if ($strategy === '') {
			$strategy = self::STRATEGY_AI_GUARDED;
		}

		return new self(
			strategy: $strategy,
			repairEnabled: self::toBool($data['repair_enabled'] ?? true),
			confidenceThreshold: max(0.0, min(1.0, (float)($data['confidence_threshold'] ?? 0.7)))
		);
	}

	public function getStrategy(): string {
		return $this->strategy;
	}

	public function isRepairEnabled(): bool {
		return $this->repairEnabled;
	}

	public function getConfidenceThreshold(): float {
		return $this->confidenceThreshold;
	}

	/** @return array<string,mixed> */
	public function toArray(): array {
		return [
			'strategy' => $this->strategy,
			'repair_enabled' => $this->repairEnabled,
			'confidence_threshold' => $this->confidenceThreshold
		];
	}

	private static function toBool(mixed $value): bool {
		if (is_bool($value)) {
			return $value;
		}
		if (is_int($value) || is_float($value)) {
			return $value !== 0;
		}
		return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
	}
}
