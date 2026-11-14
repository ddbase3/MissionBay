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

namespace Base3\MissionBay\Api;

/**
 * Expands stored component presets into flow resource definitions.
 */
interface IAgentComponentPresetFlowExpander {

	/**
	 * @param array<string,mixed> $flow
	 * @param array<int,string> $presetIds
	 * @return array{
	 *     flow:array<string,mixed>,
	 *     resource_ids:array<string,string>,
	 *     warnings:array<int,string>
	 * }
	 */
	public function expand(array $flow, array $presetIds): array;
}
