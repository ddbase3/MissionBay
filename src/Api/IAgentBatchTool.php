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
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentMutationCommitSnapshot;
use AssistantFoundation\Dto\AiToolCall;

/**
 * Optional capability for the single generic MissionBay batch coordinator.
 *
 * A batch tool is an approval envelope. It does not execute target tools from
 * callTool(). After approval it expands the server-owned snapshot into normal
 * child calls which continue through the existing action, policy, commit-guard,
 * and tool-execution pipeline.
 */
interface IAgentBatchTool extends IAgentTool, IAgentMutationGuardedTool {

	public function isBatchFunction(string $name): bool;

	/**
	 * @return array<int,AiToolCall>
	 */
	public function expandApprovedBatch(
		AgentAction $action,
		AgentMutationCommitSnapshot $snapshot,
		IAgentContext $context,
		string $interactionRequestId = ''
	): array;
}
