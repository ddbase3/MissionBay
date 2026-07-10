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

/**
 * IConfirmableAgentTool
 *
 * Allows tools to request an explicit confirmation before a tool call is
 * executed. The caller stores the returned confirmation request and later
 * executes or declines it through the confirmation workflow.
 */
interface IConfirmableAgentTool {

	/**
	 * Builds a confirmation request for a tool call.
	 *
	 * Return null when the call can be executed immediately.
	 *
	 * @param string $name Name of the function as declared in getToolDefinitions
	 * @param array<string, mixed> $arguments Arguments passed to the tool call
	 * @param IAgentContext $context Flow execution context
	 * @return array<string, mixed>|null Confirmation request data or null
	 */
	public function getConfirmationRequest(string $name, array $arguments, IAgentContext $context): ?array;

}
