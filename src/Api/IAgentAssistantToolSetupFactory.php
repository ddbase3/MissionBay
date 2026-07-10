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

use AssistantFoundation\Api\IAgentContext;

use MissionBay\Dto\Assistant\AgentAssistantToolSetup;

interface IAgentAssistantToolSetupFactory {

	/**
	 * @param array<int,IAgentTool> $tools
	 */
	public function create(array $tools, ?IAgentProfileSelector $profileSelector, string $prompt, string $system, IAgentContext $context): AgentAssistantToolSetup;
}
