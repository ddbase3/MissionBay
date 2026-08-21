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
 * Optional invocation-specific confirmation/review capability.
 *
 * Direct in-process callers may use the returned request to decide whether they
 * need confirmation. The policy-controlled agent harness may also use a
 * non-null request as server-owned presentation data for an approval that was
 * already required by an action policy. A null result must never be interpreted
 * by that harness as permission to bypass policy-required approval.
 *
 * This contract remains separate from IAgentMutationGuardedTool. Guarded tools
 * create AgentActionReview data from a server-owned commit snapshot and
 * revalidate that snapshot before execution. IConfirmableAgentTool does not
 * provide commit guarding.
 *
 * Implementing this interface does not mark a function as mutating and does not
 * replace mutation, requiresApproval or commitGuardRequired annotations in
 * getToolDefinitions(). Wrappers exposing this capability under configured
 * names must translate the effective function name before delegation.
 */
interface IConfirmableAgentTool {

	/**
	 * Builds invocation-specific confirmation/review data.
	 *
	 * Return null when no tool-provided review is available. A policy-controlled
	 * agent run must never interpret null as permission to bypass approval already
	 * required by an action policy.
	 *
	 * @param string $name Name of the function as declared in getToolDefinitions
	 * @param array<string, mixed> $arguments Arguments passed to the tool call
	 * @param IAgentContext $context Flow execution context
	 * @return array<string, mixed>|null Confirmation request data or null
	 */
	public function getConfirmationRequest(string $name, array $arguments, IAgentContext $context): ?array;

}
