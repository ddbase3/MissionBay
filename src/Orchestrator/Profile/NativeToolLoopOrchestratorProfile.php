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

final class NativeToolLoopOrchestratorProfile implements IAgentOrchestratorProfile {

	public function __construct(private readonly ITranslation $translation) {}

	public static function getName(): string {
		return 'nativetoollooporchestratorprofile';
	}

	public static function getRuntimeId(): string {
		return 'missionbay';
	}

	public function getId(): string {
		return 'native-tool-loop';
	}

	public function getSettings(): array {
		return [
			'label' => $this->translation->translate('OrchestratorProfiles', self::getName(), 'label'),
			'description' => $this->translation->translate('OrchestratorProfiles', self::getName(), 'description'),
			'enabled' => true,
			'mode' => AgentOrchestratorProfile::MODE_SIMPLE,
			'max_tool_loops' => 10,
			'model_decision' => AgentModelDecisionConfig::native()->toArray(),
			'optional_stages' => [
				'capability-discovery' => false,
				'capability-selection' => false,
				'ai-capability-selection' => false,
				'context-compaction' => false,
				'semantic-verification' => false
			],
			'capability_selection' => [
				'enabled' => false,
				'strategy' => 'hybrid',
				'max_tools' => 512,
				'select_all_threshold' => 512,
				'sticky' => false
			]
		];
	}
}
