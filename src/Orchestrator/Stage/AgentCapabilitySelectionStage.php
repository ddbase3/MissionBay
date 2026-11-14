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

namespace Base3\MissionBay\Orchestrator\Stage;

use Base3\AssistantFoundation\Api\IAgentCapabilitySelector;
use Base3\AssistantFoundation\Api\IAgentStage;

/**
 * Selects a bounded tool subset without an additional AI model call.
 */
final class AgentCapabilitySelectionStage extends AbstractAgentCapabilitySelectionStage {

	public function __construct(
		IAgentCapabilitySelector $selector,
		string $id = 'capability-selection',
		string $stageName = 'capability-selection',
		int $maxContextCharacters = 24000
	) {
		parent::__construct($selector, $id, $stageName, $maxContextCharacters);
	}

	public static function getName(): string {
		return 'agentcapabilityselectionstage';
	}

	public function getDescription(): string {
		return 'Preselects a bounded relevant tool set through deterministic filters and ranking before each model decision.';
	}

	public function getAiUsage(): string {
		return IAgentStage::AI_USAGE_NONE;
	}
}
