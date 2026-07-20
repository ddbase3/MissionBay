<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Orchestrator\Profile;

use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;

/**
 * Immutable, runtime-ready orchestration profile.
 *
 * Stage ordering is intentionally not configurable. Profiles only toggle
 * optional stages inside the canonical MissionBay pipeline.
 */
final class AgentOrchestratorProfile {

	private AgentModelDecisionConfig $modelDecision;

	public const MODE_SIMPLE = 'simple';
	public const MODE_STANDARD = 'standard';
	public const MODE_DELIBERATE = 'deliberate';
	public const MODE_GOVERNED = 'governed';

	/** @var array<int,string> */
	private const MODES = [
		self::MODE_SIMPLE,
		self::MODE_STANDARD,
		self::MODE_DELIBERATE,
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
		private readonly bool $aiCapabilitySelectionEnabled,
		private readonly bool $contextCompactionEnabled,
		private readonly bool $semanticVerificationEnabled,
		private readonly AgentCapabilitySelectionConfig $capabilitySelection,
		private readonly bool $deliberatePlanningEnabled = false,
		private readonly bool $builtin = false,
		?AgentModelDecisionConfig $modelDecision = null
	) {
		$this->modelDecision = $modelDecision ?? AgentModelDecisionConfig::aiGuarded();
		if (trim($this->id) === '') {
			throw new \InvalidArgumentException('Orchestrator profile id must not be empty.');
		}
		if (!in_array($this->mode, self::MODES, true)) {
			throw new \InvalidArgumentException('Unknown orchestrator profile mode: ' . $this->mode);
		}
		if ($this->maxToolLoops < 1 || $this->maxToolLoops > 100) {
			throw new \InvalidArgumentException('Max tool loops must be between 1 and 100.');
		}
		if ($this->capabilitySelectionEnabled && $this->aiCapabilitySelectionEnabled) {
			throw new \InvalidArgumentException('Deterministic and AI capability selection stages are mutually exclusive.');
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
	public function getModelDecision(): AgentModelDecisionConfig { return $this->modelDecision; }
	public function isDeliberatePlanningEnabled(): bool { return $this->deliberatePlanningEnabled; }
	public function isCapabilitySelectionEnabled(): bool { return $this->capabilitySelectionEnabled; }
	public function isAiCapabilitySelectionEnabled(): bool { return $this->aiCapabilitySelectionEnabled; }

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
		if ($this->aiCapabilitySelectionEnabled) {
			$stageIds[] = 'ai-capability-selection';
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
			'ai-capability-selection' => $this->aiCapabilitySelectionEnabled,
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
			'model_decision' => $this->modelDecision->toArray(),
			'deliberate_planning' => $this->deliberatePlanningEnabled,
			'builtin' => $this->builtin
		];
	}
}
