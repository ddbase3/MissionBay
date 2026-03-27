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

/**
 * Factory interface for creating memory handler instances used by agent contexts.
 */
interface IAgentMemoryFactory {

	/**
	 * Creates a memory handler by type (e.g., "nomemory", "sessionmemory").
	 *
	 * @param string $type
	 * @return IAgentMemory
	 */
	public function createMemory(string $type = 'nomemory'): IAgentMemory;
}

