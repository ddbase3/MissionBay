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

namespace MissionBay\Orchestrator\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;

/**
 * Enforces that a tool call belongs to the exact capability selection shown to
 * the model that produced the call.
 */
final class AgentCapabilitySelectionGuardService {

	public function isEnforced(IAgentContext $context): bool {
		return $context->getVar(AgentToolLoopContextKeys::CAPABILITY_SELECTION_APPLIED) === true;
	}

	public function isAllowed(IAgentContext $context, string $toolName): bool {
		if (!$this->isEnforced($context)) {
			return true;
		}
		$selected = $context->getVar(AgentToolLoopContextKeys::SELECTED_TOOL_NAMES);
		return is_array($selected) && in_array($toolName, $selected, true);
	}

	public function createFailure(
		IAgentContext $context,
		AiToolCall $call,
		int $iteration
	): AgentToolResult {
		$selected = $context->getVar(AgentToolLoopContextKeys::SELECTED_TOOL_NAMES);
		$selected = is_array($selected) ? array_values($selected) : [];
		return AgentToolResult::failure(
			$call->getId(),
			$call->getName(),
			$call->getArguments(),
			'capability_not_selected',
			'The requested tool was not part of the capability selection exposed to this model call.',
			[
				'iteration' => $iteration,
				'selection_size' => count($selected),
				'retryable' => true
			]
		);
	}
}
