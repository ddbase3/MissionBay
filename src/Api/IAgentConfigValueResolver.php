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

/**
 * Interface for resolving agent configuration values at runtime.
 *
 * This resolver is MissionBay-specific and may support agent runtime modes
 * in addition to the generic BASE3 config value modes.
 */
interface IAgentConfigValueResolver {

	/**
	 * Resolves a value from an agent config specification.
	 *
	 * Scalars are returned as-is. Generic config value modes are delegated to
	 * the BASE3 config value resolver. Agent-specific modes may be handled by
	 * the concrete MissionBay implementation.
	 *
	 * @param array|string|int|float|bool|null $config Raw agent config value definition
	 * @return mixed Resolved runtime value
	 */
	public function resolveValue(array|string|int|float|bool|null $config): mixed;
}
