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

use MissionBay\Dto\AgentParsedContent;

/**
 * Chunkers split parsed content into embeddings-friendly chunks.
 * They are priority-driven and selected dynamically by supports().
 */
interface IAgentChunker {

	/**
	 * Priority for chunker selection.
	 * Lower values are chosen first.
	 */
	public function getPriority(): int;

	/**
	 * Whether this chunker supports the parsed content.
	 * @param AgentParsedContent $parsed
	 * @return bool
	 */
	public function supports(AgentParsedContent $parsed): bool;

	/**
	 * Creates chunks:
	 * [
	 * 	 ['id' => string, 'text' => string, 'meta' => array],
	 * 	 ...
	 * ]
	 * @param AgentParsedContent $parsed
	 * @return array<int,array<string,mixed>>
	 */
	public function chunk(AgentParsedContent $parsed): array;
}
