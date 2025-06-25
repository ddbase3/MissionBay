<?php declare(strict_types=1);

namespace MissionBay\Agent;

use MissionBay\Api\IAgentRouter;
use MissionBay\Api\IAgentRouterFactory;
use Base3\Api\IClassMap;

class AgentRouterFactory implements IAgentRouterFactory {

	public function __construct(private readonly IClassMap $classmap) {}

	public function createRouter(string $type = 'strictconnectionrouter'): IAgentRouter {
		$outer = $this->classmap->getInstanceByInterfaceName(IAgentRouter::class, $type);

		if (!$router instanceof IAgentRouter) {
			throw new \RuntimeException("Router type '$type' could not be instantiated or is invalid");
		}

		return $router;
	}
}

