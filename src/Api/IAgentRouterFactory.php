<?php declare(strict_types=1);

namespace MissionBay\Api;

/**
 * Factory interface for creating router instances used by agent contexts.
 */
interface IAgentRouterFactory {

	/**
	 * Creates a router by type (e.g., "strictconnectionrouter").
	 *
	 * @param string $type
	 * @return IAgentRouter
	 */
	public function createRouter(string $type = 'strictconnectionrouter'): IAgentRouter;
}

