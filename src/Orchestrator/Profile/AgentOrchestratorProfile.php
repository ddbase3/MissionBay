<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Orchestrator\Profile;

use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;

/**
 * Immutable, runtime-ready orchestration profile.
 *
 * Stage ordering is intentionally not configurable. Profiles only toggle
 * optional stages inside the canonical MissionBay pipeline.
 */
final class AgentOrchestratorProfile {

	public const MODE_SIMPLE = 'simple';
	public const MODE_STANDARD = 'standard';
	public const MODE_GOVERNED = 'governed';

	/** @var array<int,string> */
	private const MODES = [
		self::MODE_SIMPLE,
		self::MODE_STANDARD,
		self::MODE_GOVERNED
	];

	public function __construct(
		private readonly string $id,
		private readonly string $label,
		private readonly string $description,
		private readonly bool $enabled,
		private readonly string $mode,
		private readonly int $maxToolLoops,
		private readonly bool $capabilityDiscoveryEnabled,
		private readonly bool $capabilitySelectionEnabled,
		private readonly bool $contextCompactionEnabled,
		private readonly bool $semanticVerificationEnabled,
		private readonly AgentCapabilitySelectionConfig $capabilitySelection,
		private readonly bool $builtin = false
	) {
		if (trim($this->id) === '') {
			throw new \InvalidArgumentException('Orchestrator profile id must not be empty.');
		}
		if (!in_array($this->mode, self::MODES, true)) {
			throw new \InvalidArgumentException('Unknown orchestrator profile mode: ' . $this->mode);
		}
		if ($this->maxToolLoops < 1 || $this->maxToolLoops > 100) {
			throw new \InvalidArgumentException('Max tool loops must be between 1 and 100.');
		}
	}

	public function getId(): string { return $this->id; }
	public function getLabel(): string { return $this->label; }
	public function getDescription(): string { return $this->description; }
	public function isEnabled(): bool { return $this->enabled; }
	public function getMode(): string { return $this->mode; }
	public function getMaxToolLoops(): int { return $this->maxToolLoops; }
	public function isBuiltin(): bool { return $this->builtin; }
	public function getCapabilitySelection(): AgentCapabilitySelectionConfig { return $this->capabilitySelection; }

	/**
	 * Returns the canonical ordered stage ids. Required stages cannot be
	 * disabled and callers never receive a freely reordered pipeline.
	 *
	 * @return array<int,string>
	 */
	public function getStageIds(): array {
		$stageIds = [];

		if ($this->capabilityDiscoveryEnabled) {
			$stageIds[] = 'capability-discovery';
		}
		if ($this->capabilitySelectionEnabled) {
			$stageIds[] = 'capability-selection';
		}

		$stageIds[] = 'model-decision';
		$stageIds[] = 'action-policy';
		$stageIds[] = 'tool-execution';

		if ($this->contextCompactionEnabled) {
			$stageIds[] = 'context-compaction';
		}

		$stageIds[] = 'tool-observation';

		if ($this->semanticVerificationEnabled) {
			$stageIds[] = 'semantic-verification';
		}

		return $stageIds;
	}

	/** @return array<string,bool> */
	public function getOptionalStages(): array {
		return [
			'capability-discovery' => $this->capabilityDiscoveryEnabled,
			'capability-selection' => $this->capabilitySelectionEnabled,
			'context-compaction' => $this->contextCompactionEnabled,
			'semantic-verification' => $this->semanticVerificationEnabled
		];
	}

	/** @return array<string,mixed> */
	public function toArray(): array {
		return [
			'id' => $this->id,
			'label' => $this->label,
			'description' => $this->description,
			'enabled' => $this->enabled,
			'mode' => $this->mode,
			'max_tool_loops' => $this->maxToolLoops,
			'optional_stages' => $this->getOptionalStages(),
			'stage_ids' => $this->getStageIds(),
			'capability_selection' => $this->capabilitySelection->toArray(),
			'builtin' => $this->builtin
		];
	}
}
