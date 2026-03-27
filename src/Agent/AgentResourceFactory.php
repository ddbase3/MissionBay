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

use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Api\IAgentResource;
use Base3\Api\IClassMap;

class AgentResourceFactory implements IAgentResourceFactory {

	public function __construct(private readonly IClassMap $classmap) {}

	public function createResource(string $type): ?IAgentResource {
		$resource = $this->classmap->getInstanceByInterfaceName(IAgentResource::class, $type);
		return $resource instanceof IAgentResource ? $resource : null;
	}
}

