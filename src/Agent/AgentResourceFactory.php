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

use Base3\Api\IClassMap;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentResourceFactory;

class AgentResourceFactory implements IAgentResourceFactory {

	public function __construct(private readonly IClassMap $classmap) {}

	public function createResource(string $type): ?IAgentResource {
		$class = $this->classmap->getClassByInterfaceName(IAgentResource::class, $type);
		if (!is_string($class) || $class === '') {
			return null;
		}

		// Flow resources are configured runtime instances. Always construct a fresh
		// object instead of reusing the class-map instance cache, otherwise two
		// component presets of the same implementation can overwrite each other's
		// id and configuration.
		$resource = $this->classmap->instantiate($class);

		return $resource instanceof IAgentResource ? $resource : null;
	}
}
