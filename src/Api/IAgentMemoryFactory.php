<?php declare(strict_types=1);

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

