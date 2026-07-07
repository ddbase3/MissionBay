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

use Base3\Api\IBase;

/**
 * IAgentResourceProvider
 *
 * Provides read-only agent resources. Resources describe contextual data that can
 * be listed and read by agent runtimes without executing an action.
 */
interface IAgentResourceProvider extends IBase {

	/**
	 * Returns resource definitions exposed by this provider.
	 *
	 * Concrete resources should contain at least uri, name, title and mimeType.
	 * Template resources may use uriTemplate instead of uri and are exposed through
	 * transports that support resource templates.
	 *
	 * @param IAgentContext $context
	 * @return array<int, array<string, mixed>>
	 */
	public function getResourceDefinitions(IAgentContext $context): array;

	/**
	 * Reads one resource by URI.
	 *
	 * Return null when this provider does not own the URI.
	 *
	 * @param string $uri
	 * @param IAgentContext $context
	 * @return array<string, mixed>|null
	 */
	public function readResource(string $uri, IAgentContext $context): ?array;

}
