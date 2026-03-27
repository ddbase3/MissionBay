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
 * Interface for resolving dynamic configuration values at runtime.
 * Supports various resolution strategies (fixed, env, config, etc).
 */
interface IAgentConfigValueResolver {

	/**
	 * Resolve a value from a config specification.
	 *
	 * Scalars are returned as-is.
	 *
	 * @param array|string|int|float|bool|null $config
	 * @return mixed
	 */
	public function resolveValue(array|string|int|float|bool|null $config): mixed;
}
