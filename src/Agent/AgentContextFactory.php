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

		if ($memory !== null) $context->setMemory($memory);

		foreach ($vars as $key => $value) {
			$context->setVar($key, $value);
		}

		return $context;
	}
}

