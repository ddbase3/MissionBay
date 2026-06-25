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

use Base3\Logger\Api\ILogger;

interface IAgentAssistantMemoryService {

	/**
	 * @param array<int,IAgentMemory> $memories
	 * @return array<int,IAgentMemory>
	 */
	public function sortMemories(array $memories): array;

	/**
	 * @param array<int,IAgentMemory> $memories
	 * @return array<int,array<string,mixed>>
	 */
	public function buildInitialMessages(string $system, array $memories, string $nodeId, ?ILogger $logger = null): array;

	/**
	 * @param array<int,IAgentMemory> $memories
	 * @param array<string,mixed> $message
	 */
	public function appendVisibleMessage(array $memories, string $nodeId, array $message, ?ILogger $logger = null): void;
}
