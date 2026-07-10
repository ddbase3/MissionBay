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

use AssistantFoundation\Dto\AiSearchResult;
use Base3\Api\IBase;

interface ISearchService extends IBase {

	/**
	 * Executes a model-backed search and returns normalized provider and usage
	 * metadata together with answer, results, and citations.
	 *
	 * @param array<string,mixed> $options
	 */
	public function searchResult(string $query, array $options = []): AiSearchResult;

	/**
	 * @param array<string,mixed> $options
	 */
	public function setOptions(array $options): void;

	/**
	 * @return array<string,mixed>
	 */
	public function getOptions(): array;

	/**
	 * @param array<string,mixed> $options
	 * @return array<string,mixed>
	 */
	public function search(string $query, array $options = []): array;
}
