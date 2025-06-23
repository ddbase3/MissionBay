<?php declare(strict_types=1);

namespace MissionBay\Agent;

use MissionBay\Api\IAgentMemory;
use MissionBay\Api\IAgentMemoryFactory;
use Base3\Api\IClassMap;

class AgentMemoryFactory implements IAgentMemoryFactory {

	public function __construct(private readonly IClassMap $classmap) {}

	public function createMemory(string $type = 'nomemory'): IAgentMemory {
		$memory = $this->classmap->getInstanceByInterfaceName(IAgentMemory::class, $type);

		if (!$memory instanceof IAgentMemory) {
			throw new \RuntimeException("Memory type '$type' could not be instantiated or is invalid");
		}

		return $memory;
	}
}

