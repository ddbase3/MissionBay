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

use MissionBay\Dto\Assistant\AgentAssistantTurnOptions;
use MissionBay\Dto\Assistant\AgentAssistantTurnResources;
use MissionBay\Dto\Assistant\AgentAssistantTurnResult;

interface IAgentAssistantTurnService {

	/**
	 * @param ?callable $eventCallback function(string $event, array $payload): void
	 */
	public function run(AgentAssistantTurnResources $resources, IAgentContext $context, AgentAssistantTurnOptions $options, ?callable $eventCallback = null): AgentAssistantTurnResult;
}
