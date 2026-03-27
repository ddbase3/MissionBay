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

use MissionBay\Api\IAgentResource;

/**
 * Factory interface for instantiating agent resources by type.
 */
interface IAgentResourceFactory {

	/**
	 * Creates an instance of an agent resource based on the given type name.
	 *
	 * @param string $type Type identifier of the resource (typically matches getName()).
	 * @return IAgentResource|null New instance of the resource, or null if type is unknown.
	 */
	public function createResource(string $type): ?IAgentResource;
}

