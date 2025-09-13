<?php declare(strict_types=1);

namespace MissionBay\Api;

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

