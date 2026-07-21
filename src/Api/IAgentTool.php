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
 * Core contract for a callable tool that an agent runtime can expose to a chat
 * model. One tool class may publish one or many function definitions, and may
 * mix read-only functions with mutating functions.
 *
 * Each function definition is the authoritative input contract for that
 * operation. Besides the OpenAI-style function schema, MissionBay reads
 * top-level semantic annotations such as readOnlyHint, mutation,
 * requiresApproval, commitGuardRequired, sideEffectHint and destructiveHint.
 * These annotations are evaluated per function; implementing IAgentTool does
 * not make the complete class read-only or mutating.
 *
 * Mutating functions with commitGuardRequired=true must be provided by a tool
 * that also implements IAgentMutationGuardedTool. That capability captures the
 * state shown to the user, creates the user-facing AgentActionReview, and
 * revalidates the state immediately before callTool() may perform the write.
 *
 * IConfirmableAgentTool is a separate compatibility capability used by the
 * direct/MCP confirmation flow. It must not be confused with the
 * policy-controlled guarded mutation lifecycle.
 *
 * Tool implementations should remain transport-neutral. They describe and
 * execute operations; Chatbot, MCP and administration UIs decide how the
 * definitions, reviews and results are rendered.
 */
interface IAgentTool extends IBase {

	/**
	 * Returns all callable function definitions exposed by this tool.
	 *
	 * The schema under function.parameters describes input arguments. Resource
	 * configuration belongs to ISchemaProvider, while operation result schemas
	 * belong to IOutputSchemaProvider. Do not use either of those contracts as a
	 * replacement for function.parameters.
	 *
	 * Every operation should explicitly declare its safety semantics. Typical
	 * read-only annotations are readOnlyHint=true, mutation=false and
	 * requiresApproval=false. A guarded mutation normally declares
	 * readOnlyHint=false, mutation=true, requiresApproval=true,
	 * commitGuardRequired=true and sideEffectHint=true.
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

