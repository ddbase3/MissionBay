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

namespace Base3\MissionBay\Orchestrator\Service;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentMutationCommitSnapshot;
use Base3\AssistantFoundation\Dto\AiToolCall;
use Base3\MissionBay\Api\IAgentBatchTool;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;

/** Expands an approved batch envelope into normal guarded child tool calls. */
final class AgentBatchExecutionService {

	public function __construct(
		private readonly AgentMutationCommitGuardService $mutationCommitGuardService
	) {}

	public function isBatchCall(AiToolCall $call, IAgentContext $context): bool {
		$tool = $this->findBatchTool($call->getName(), $context);
		return $tool instanceof IAgentBatchTool && $tool->isBatchFunction($call->getName());
	}

	/** @return array<int,AiToolCall> */
	public function expandApprovedCall(AiToolCall $call, IAgentContext $context): array {
		$tool = $this->findBatchTool($call->getName(), $context);
		if (!$tool instanceof IAgentBatchTool || !$tool->isBatchFunction($call->getName())) {
			throw new \RuntimeException('Approved call is not a MissionBay batch envelope.');
		}

		$decision = $this->mutationCommitGuardService->validate($call, $context);
		if (!$decision->isAllowed()) {
			throw new \RuntimeException($decision->getReason());
		}

		$snapshotData = $call->getMetadata()[AgentMutationCommitGuardService::TOOL_CALL_METADATA_SNAPSHOT] ?? null;
		if (!is_array($snapshotData)) {
			throw new \RuntimeException('Approved batch call contains no mutation snapshot.');
		}
		$snapshot = AgentMutationCommitSnapshot::fromArray($snapshotData);
		$action = new AgentAction(
			$call->getId(),
			AgentAction::TYPE_TOOL_CALL,
			$call->getName(),
			$call->getArguments(),
			['tool_call' => $call->getMetadata()]
		);
		$interactionRequestId = trim((string)(
			$call->getMetadata()[AgentMutationCommitGuardService::TOOL_CALL_METADATA_INTERACTION_REQUEST] ?? ''
		));

		return $tool->expandApprovedBatch($action, $snapshot, $context, $interactionRequestId);
	}

	private function findBatchTool(string $toolName, IAgentContext $context): ?IAgentBatchTool {
		$tools = $context->getVar(AgentToolLoopContextKeys::TOOLS);
		if (!is_array($tools)) {
			return null;
		}
		foreach ($tools as $tool) {
			if (!$tool instanceof IAgentBatchTool || !$tool instanceof IAgentTool) {
				continue;
			}
			foreach ($tool->getToolDefinitions() as $definition) {
				$function = is_array($definition['function'] ?? null) ? $definition['function'] : $definition;
				if (trim((string)($function['name'] ?? '')) === $toolName) {
					return $tool;
				}
			}
		}
		return null;
	}
}
