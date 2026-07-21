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
 * Optional compatibility contract for callers, especially the direct MCP tool
 * endpoint, where the tool itself decides whether one concrete invocation must
 * be confirmed before it is executed.
 *
 * This contract combines the confirmation decision with legacy array-based
 * presentation data. It is intentionally separate from
 * IAgentMutationGuardedTool. In the policy-controlled agent harness, action
 * policies decide whether approval is required, while guarded mutation tools
 * create an AgentActionReview from a server-owned commit snapshot and validate
 * that snapshot before execution.
 *
 * Implementing this interface does not mark a function as mutating and does not
 * replace mutation, requiresApproval or commitGuardRequired annotations in
 * getToolDefinitions(). Wrappers exposing this capability under configured
 * names must translate the effective function name before delegation.
 */
interface IConfirmableAgentTool {

	/**
	 * Builds the direct/MCP confirmation request for a tool invocation.
	 *
	 * Return null when this direct caller may execute the call immediately. In a
	 * policy-controlled agent run, null must never be interpreted as permission
	 * to bypass an approval already required by an action policy.
	 *
	 * @param string $name Name of the function as declared in getToolDefinitions
	 * @param array<string, mixed> $arguments Arguments passed to the tool call
	 * @param IAgentContext $context Flow execution context
	 * @return array<string, mixed>|null Confirmation request data or null
	 */
	public function getConfirmationRequest(string $name, array $arguments, IAgentContext $context): ?array;

}
