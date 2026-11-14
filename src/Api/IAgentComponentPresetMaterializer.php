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

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\MissionBay\Dto\AgentComponentPresetMaterialization;

/**
 * Materializes one stored agent component preset into fresh configured runtime resources.
 */
interface IAgentComponentPresetMaterializer {

	/**
	 * Creates an agent context suitable for preset materialization and isolated tests.
	 *
	 * @param array<string,mixed> $vars
	 */
	public function createContext(array $vars = []): IAgentContext;

	/**
	 * Materializes one preset including recursive dock dependencies and capability wrappers.
	 */
	public function materialize(string $presetId, IAgentContext $context): AgentComponentPresetMaterialization;
}
