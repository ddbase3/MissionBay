<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Orchestrator;

use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Dto\AgentStageMount;
use AssistantFoundation\Dto\AgentStageSlot;
use Base3\Api\IComponentResolver;

/**
 * Resolves configured core stages and mounts run-local module stages into
 * stable semantic slots without registering those stages globally.
 */
final class AgentStagePipelineResolver {

	/** @var array<int,string> */
	private const CANONICAL_STAGE_IDS = [
		'capability-discovery',
		'capability-selection',
		'ai-capability-selection',
		'model-decision',
		'action-policy',
		'tool-execution',
		'context-compaction',
		'tool-observation',
		'semantic-verification',
		'final-answer-regenerate'
	];

	/** @var array<int,string> */
	private const CAPABILITY_SELECTION_STAGE_IDS = [
		'capability-selection',
		'ai-capability-selection'
	];

	/** @var array<int,string> */
	private const REQUIRED_STAGE_IDS = [
		'model-decision',
		'action-policy',
		'tool-execution',
		'tool-observation'
	];

	/** @var array<int,string> */
	private array $defaultStageIds;

	/** @param array<int,string> $defaultStageIds */
	public function __construct(
		private readonly IComponentResolver $componentResolver,
		array $defaultStageIds
	) {
		if ($defaultStageIds === []) {
			throw new \RuntimeException('Default agent stage pipeline must not be empty.');
		}
		$this->defaultStageIds = $this->validateCanonicalStageIds($this->normalizeExplicitStageIds($defaultStageIds));
	}

	/**
	 * @param array<int,string> $stageIds
	 * @param array<int,AgentStageMount> $stageMounts
	 * @return array<int,IAgentStage>
	 */
	public function resolve(array $stageIds = [], array $stageMounts = []): array {
		$stageIds = $stageIds === []
			? $this->defaultStageIds
			: $this->validateCanonicalStageIds($this->normalizeExplicitStageIds($stageIds));
		$stages = [];

		foreach ($stageIds as $stageId) {
			$stage = $this->componentResolver->get(IAgentStage::class, $stageId);
			if (!$stage instanceof IAgentStage) {
				throw new \RuntimeException('Configured agent stage could not be resolved: ' . $stageId);
			}
			$stages[] = $stage;
		}

		return $this->applyMounts($stages, $stageMounts);
	}

	/**
	 * @param array<int,IAgentStage> $stages
	 * @param array<int,AgentStageMount> $mounts
	 * @return array<int,IAgentStage>
	 */
	private function applyMounts(array $stages, array $mounts): array {
		if ($mounts === []) {
			return $stages;
		}

		$bySlot = [];
		foreach ($mounts as $mount) {
			if (!$mount instanceof AgentStageMount) {
				throw new \RuntimeException('Module stage mounts must contain only AgentStageMount values.');
			}
			$bySlot[$mount->getSlot()][] = $mount;
		}

		foreach ($bySlot as &$slotMounts) {
			usort($slotMounts, static function(AgentStageMount $a, AgentStageMount $b): int {
				$cmp = $a->getOrder() <=> $b->getOrder();
				return $cmp !== 0 ? $cmp : strcmp($a->getStage()->id(), $b->getStage()->id());
			});
		}
		unset($slotMounts);

		$before = [
			'model-decision' => [AgentStageSlot::BEFORE_PLANNING, AgentStageSlot::PLANNING],
			'action-policy' => [AgentStageSlot::BEFORE_EXECUTION],
			'tool-execution' => [AgentStageSlot::EXECUTION, AgentStageSlot::BEFORE_TOOL_CALL],
			'semantic-verification' => [AgentStageSlot::BEFORE_FINAL_ANSWER]
		];
		$after = [
			'tool-execution' => [AgentStageSlot::AFTER_TOOL_CALL],
			'semantic-verification' => [AgentStageSlot::AFTER_FINAL_ANSWER]
		];
		$result = [];
		$consumed = [];

		foreach ($stages as $stage) {
			foreach ($before[$stage->id()] ?? [] as $slot) {
				foreach ($bySlot[$slot] ?? [] as $mount) {
					$result[] = $mount->getStage();
				}
				$consumed[$slot] = true;
			}

			$result[] = $stage;

			foreach ($after[$stage->id()] ?? [] as $slot) {
				foreach ($bySlot[$slot] ?? [] as $mount) {
					$result[] = $mount->getStage();
				}
				$consumed[$slot] = true;
			}
		}

		foreach (array_keys($bySlot) as $slot) {
			if (!isset($consumed[$slot])) {
				throw new \RuntimeException('Cannot mount module stage because the selected pipeline has no anchor for slot: ' . $slot);
			}
		}

		$known = [];
		foreach ($result as $stage) {
			$id = trim($stage->id());
			if ($id === '') {
				throw new \RuntimeException('Agent stage ids must not be empty.');
			}
			if (isset($known[$id])) {
				throw new \RuntimeException('Duplicate agent stage id after module mounts: ' . $id);
			}
			$known[$id] = true;
		}

		return $result;
	}

	/**
	 * Core stage order is a framework invariant. Custom behavior belongs in
	 * semantic module mount slots, not in freely reordered core stage lists.
	 *
	 * @param array<int,string> $stageIds
	 * @return array<int,string>
	 */
	private function validateCanonicalStageIds(array $stageIds): array {
		$positions = array_flip(self::CANONICAL_STAGE_IDS);
		$lastPosition = -1;
		$known = array_fill_keys($stageIds, true);

		foreach (self::REQUIRED_STAGE_IDS as $requiredStageId) {
			if (!isset($known[$requiredStageId])) {
				throw new \RuntimeException('Required agent stage is missing: ' . $requiredStageId);
			}
		}

		$selectionStages = array_values(array_intersect(self::CAPABILITY_SELECTION_STAGE_IDS, $stageIds));
		if (count($selectionStages) > 1) {
			throw new \RuntimeException('Capability selection stages are mutually exclusive: ' . implode(', ', $selectionStages));
		}

		foreach ($stageIds as $stageId) {
			if (!array_key_exists($stageId, $positions)) {
				throw new \RuntimeException('Core agent stage is not part of the canonical pipeline: ' . $stageId);
			}
			$position = (int)$positions[$stageId];
			if ($position <= $lastPosition) {
				throw new \RuntimeException('Invalid agent stage order near: ' . $stageId);
			}
			$lastPosition = $position;
		}

		if ($selectionStages !== [] && !isset($known['model-decision'])) {
			throw new \RuntimeException('Capability selection requires model decision.');
		}

		return $stageIds;
	}

	/**
	 * @param array<int,string> $stageIds
	 * @return array<int,string>
	 */
	private function normalizeExplicitStageIds(array $stageIds): array {
		$result = [];
		$known = [];
		foreach ($stageIds as $stageId) {
			if (!is_string($stageId)) {
				throw new \RuntimeException('Agent stage ids must be strings.');
			}
			$stageId = trim($stageId);
			if ($stageId === '') {
				throw new \RuntimeException('Agent stage ids must not be empty.');
			}
			if (isset($known[$stageId])) {
				throw new \RuntimeException('Duplicate agent stage id: ' . $stageId);
			}
			$known[$stageId] = true;
			$result[] = $stageId;
		}
		return $result;
	}
}
