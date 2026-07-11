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

namespace MissionBay\Orchestrator;

use AssistantFoundation\Api\IAgentStage;
use Base3\Api\IComponentResolver;

/**
 * AgentStagePipelineResolver
 *
 * Resolves an explicitly ordered list of configured IAgentStage component
 * ids through the general BASE3 component resolver.
 *
 * It owns no component registry and performs no component construction.
 */
final class AgentStagePipelineResolver {

	/**
	 * @var array<int,string>
	 */
	private array $defaultStageIds;

	/**
	 * @param array<int,string> $defaultStageIds
	 */
	public function __construct(
		private readonly IComponentResolver $componentResolver,
		array $defaultStageIds
	) {
		if ($defaultStageIds === []) {
			throw new \RuntimeException('Default agent stage pipeline must not be empty.');
		}

		$this->defaultStageIds = $this->normalizeExplicitStageIds($defaultStageIds);
	}

	/**
	 * @param array<int,string> $stageIds
	 * @return array<int,IAgentStage>
	 */
	public function resolve(array $stageIds = []): array {
		$stageIds = $stageIds === []
			? $this->defaultStageIds
			: $this->normalizeExplicitStageIds($stageIds);
		$stages = [];

		foreach ($stageIds as $stageId) {
			$stage = $this->componentResolver->get(IAgentStage::class, $stageId);

			if (!$stage instanceof IAgentStage) {
				throw new \RuntimeException(
					'Configured agent stage could not be resolved: ' . $stageId
				);
			}

			$stages[] = $stage;
		}

		return $stages;
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
