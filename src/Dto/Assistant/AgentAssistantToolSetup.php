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

namespace MissionBay\Dto\Assistant;

use MissionBay\Api\IAgentTool;
use MissionBay\Profile\ProfilePlan;
use MissionBay\Profile\ToolFilterReport;

final class AgentAssistantToolSetup {

	/**
	 * @param array<int,IAgentTool> $tools
	 * @param array<int,array<string,mixed>> $toolDefs
	 * @param string[]|null $allowedToolNames
	 * @param string[] $missingRequiredTools
	 */
	public function __construct(
		private array $tools,
		private array $toolDefs,
		private ProfilePlan $effectivePlan,
		private ToolFilterReport $report,
		private ?array $allowedToolNames,
		private bool $profileWasUnavailable = false,
		private array $missingRequiredTools = []
	) {
	}

	/**
	 * @return array<int,IAgentTool>
	 */
	public function getTools(): array {
		return $this->tools;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function getToolDefs(): array {
		return $this->toolDefs;
	}

	public function getEffectivePlan(): ProfilePlan {
		return $this->effectivePlan;
	}

	public function getReport(): ToolFilterReport {
		return $this->report;
	}

	/**
	 * @return string[]|null
	 */
	public function getAllowedToolNames(): ?array {
		return $this->allowedToolNames;
	}

	public function wasProfileUnavailable(): bool {
		return $this->profileWasUnavailable;
	}

	/**
	 * @return string[]
	 */
	public function getMissingRequiredTools(): array {
		return $this->missingRequiredTools;
	}
}
