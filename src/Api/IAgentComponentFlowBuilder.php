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
 * IAgentComponentFlowBuilder
 *
 * Builds an effective agent flow from a base flow and selected component presets.
 */
interface IAgentComponentFlowBuilder {

	/**
	 * Builds the effective agent flow.
	 *
	 * @param array<string,mixed> $baseFlow
	 * @param array<int,array<string,mixed>> $components
	 * @param string $assistantNodeId
	 * @return array<string,mixed>
	 */
	public function build(array $baseFlow, array $components, string $assistantNodeId = 'assistant'): array;

	/**
	 * Returns non-fatal warnings from the last build call.
	 *
	 * @return array<int,string>
	 */
	public function getWarnings(): array;
}
