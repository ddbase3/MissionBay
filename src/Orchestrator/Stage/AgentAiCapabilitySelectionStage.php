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

namespace MissionBay\Orchestrator\Stage;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use MissionBay\Capability\SemanticAgentCapabilitySelector;

/**
 * Uses the active chat model to rerank a bounded capability candidate set.
 * Provider failures and invalid output are handled by the selector fallback.
 */
final class AgentAiCapabilitySelectionStage extends AbstractAgentCapabilitySelectionStage {

	public function __construct(
		SemanticAgentCapabilitySelector $selector,
		string $id = 'ai-capability-selection',
		string $stageName = 'ai-capability-selection',
		int $maxContextCharacters = 24000
	) {
		parent::__construct($selector, $id, $stageName, $maxContextCharacters);
	}

	public static function getName(): string {
		return 'agentaicapabilityselectionstage';
	}

	public function getDescription(): string {
		return 'Uses the active chat model to select a bounded relevant tool set. Source-complete selections are reused for later model decisions in the same orchestration turn.';
	}

	public function supports(IAgentContext $context): bool {
		if (!parent::supports($context)) {
			return false;
		}

		$config = $context->getVar(AgentToolLoopContextKeys::CAPABILITY_SELECTION_CONFIG);
		if (!$config instanceof AgentCapabilitySelectionConfig || !$config->selectsSources()) {
			return true;
		}

		return $context->getVar(AgentToolLoopContextKeys::CAPABILITY_SELECTION_APPLIED) !== true;
	}

	public function getAiUsage(): string {
		return IAgentStage::AI_USAGE_CONDITIONAL;
	}

	protected function resolveModel(IAgentContext $context): ?IAiChatModel {
		$model = $context->getVar(AgentToolLoopContextKeys::MODEL);
		return $model instanceof IAiChatModel ? $model : null;
	}
}
