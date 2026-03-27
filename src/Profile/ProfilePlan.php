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

namespace MissionBay\Profile;

final class ProfilePlan {

	/**
	 * @param string[]|null $allowedTools If null, no filtering is applied.
	 * @param string[] $requiredTools Tools that must be available, otherwise plan is not feasible.
	 */
	public function __construct(
		private string $profileName,
		private ?string $systemAppend = null,
		private ?array $allowedTools = null,
		private array $requiredTools = []
	) {
	}

	public function getProfileName(): string {
		return $this->profileName;
	}

	public function getSystemAppend(): ?string {
		return $this->systemAppend;
	}

	/**
	 * @return string[]|null
	 */
	public function getAllowedTools(): ?array {
		return $this->allowedTools;
	}

	/**
	 * @return string[]
	 */
	public function getRequiredTools(): array {
		return $this->requiredTools;
	}
}
