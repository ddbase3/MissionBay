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

namespace Base3\MissionBay\Orchestrator\Profile;

use Base3\AssistantFoundation\Api\IAgentOrchestratorProfile;
use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;

final class AgentSelectedNativeOrchestratorProfile implements IAgentOrchestratorProfile {

	public function __construct(private readonly ITranslation $translation) {}

	public static function getName(): string {
		return 'agentselectednativeorchestratorprofile';
	}

	public static function getRuntimeId(): string {
		return 'missionbay';
	}

	public function getId(): string {
		return 'agent-selected-native';
	}

	public function getSettings(): array {
		return [
			'label' => $this->translation->translate('OrchestratorProfiles', self::getName(), 'label'),
			'description' => $this->translation->translate('OrchestratorProfiles', self::getName(), 'description'),
			'enabled' => true,
			'mode' => AgentOrchestratorProfile::MODE_STANDARD,
			'max_tool_loops' => 32,
			'model_decision' => AgentModelDecisionConfig::nativeCapability()->toArray(),
			'optional_stages' => [
				'capability-discovery' => true,
				'capability-selection' => false,
				'ai-capability-selection' => false,
				'context-compaction' => true,
				'semantic-verification' => false
			],
			'capability_selection' => [
				'enabled' => false,
				'strategy' => 'hybrid',
				'max_tools' => 64,
				'max_sources' => 8,
				'select_all_threshold' => 0,
				'semantic_candidate_tools' => 64,
				'semantic_max_prompt_characters' => 48000,
				'sticky' => false
			]
		];
	}
}
