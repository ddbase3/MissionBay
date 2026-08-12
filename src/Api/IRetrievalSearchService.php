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

namespace MissionBay\Api;

interface IRetrievalSearchService {

	/**
	 * Returns materializable component presets exposing retrieval_search.
	 *
	 * @param array<string,mixed> $contextMetadata
	 * @return array<int,array<string,mixed>>
	 */
	public function getSearchPresets(array $contextMetadata = []): array;

	/**
	 * @param array<string,mixed> $arguments
	 * @param array<string,mixed> $contextMetadata
	 * @return array<string,mixed>
	 */
	public function search(string $presetId, array $arguments, array $contextMetadata = []): array;

	/**
	 * @param array<string,mixed> $arguments
	 * @param array<string,mixed> $contextMetadata
	 * @return array<string,mixed>
	 */
	public function context(string $presetId, array $arguments, array $contextMetadata = []): array;
}
