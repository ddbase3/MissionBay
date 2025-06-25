<?php declare(strict_types=1);

namespace MissionBay\Api;

/**
 * Factory interface for creating agent context instances.
 */
interface IAgentContextFactory {

	/**
	 * Creates a context instance by type name.
	 *
	 * @param string $type Context class name registered in IClassMap (default = "agentcontext")
	 * @param IAgentMemory|null $memory Optional memory instance to inject
	 * @param array $vars Initial flow-scoped variables
	 * @return IAgentContext
	 */
	public function createContext(string $type = 'agentcontext', ?IAgentMemory $memory = null, array $vars = []): IAgentContext;
}

