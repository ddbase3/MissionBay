<?php declare(strict_types=1);

namespace MissionBay\Agent;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentMemory;
use Base3\Api\IClassMap;

class AgentContextFactory implements IAgentContextFactory {

	public function __construct(private readonly IClassMap $classmap) {}

	public function createContext(string $type = 'agentcontext', ?IAgentMemory $memory = null, array $vars = []): IAgentContext {
		$context = $this->classmap->getInstanceByInterfaceName(IAgentContext::class, $type);

		if (!$context instanceof IAgentContext) {
			throw new \RuntimeException("Context type '$type' could not be instantiated or is invalid");
		}

		if ($memory !== null) {
			$context->setMemory($memory);
		}

		foreach ($vars as $key => $value) {
			$context->setVar($key, $value);
		}

		return $context;
	}
}

