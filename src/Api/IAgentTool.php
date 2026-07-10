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

use Base3\Api\IBase;

/**
 * IAgentTool
 *
 * Defines a callable tool that an AiAssistantNode can expose to the chat model.
 * Tools declare their available functions and handle tool invocations.
 */
interface IAgentTool extends IBase {

	/**
	 * Returns a list of tool definitions (OpenAI-style JSON schema).
	 *
	 * Example:
	 * [
	 *   [
	 *     'name' => 'get_current_time',
	 *     'description' => 'Returns the current time.',
	 *     'parameters' => [
	 *       'type' => 'object',
	 *       'properties' => [
	 *         'format' => ['type' => 'string']
	 *       ],
	 *       'required' => []
	 *     ]
	 *   ]
	 * ]
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getToolDefinitions(): array;

	/**
	 * Executes a tool function call with given arguments.
	 *
	 * @param string $name Name of the function (as declared in getToolDefinitions)
	 * @param array<string, mixed> $arguments Arguments passed from the assistant
	 * @param IAgentContext $context Flow execution context
	 * @return mixed Result (serializable value to feed back into assistant)
	 */
	public function callTool(string $name, array $arguments, IAgentContext $context): mixed;
}

