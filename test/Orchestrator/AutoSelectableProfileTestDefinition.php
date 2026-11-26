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

namespace Base3\MissionBay\Test\Orchestrator;

use Base3\AssistantFoundation\Api\IAutoSelectableAgentOrchestratorProfile;

final class AutoSelectableProfileTestDefinition implements IAutoSelectableAgentOrchestratorProfile {

	public int $checks = 0;

	public function __construct(
		private readonly string $id,
		private readonly int $priority,
		private readonly bool $supported = true,
		private readonly bool $enabled = true,
		private readonly int $loops = 10
	) {}

	public static function getName(): string { return 'autoselectableprofiletestdefinition'; }
	public static function getRuntimeId(): string { return 'missionbay'; }
	public function getId(): string { return $this->id; }
	public function getSettings(): array { return ['enabled' => $this->enabled, 'mode' => 'standard', 'max_tool_loops' => $this->loops]; }
	public function getAutoSelectionPriority(): int { return $this->priority; }

	public function supportsAutoSelection(array $agentSettings, bool $requiresToolLoop): bool {
		$this->checks++;
		return $this->supported;
	}
}
