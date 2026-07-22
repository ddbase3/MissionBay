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

namespace MissionBay\Dto;

/**
 * Immutable result of compiling one stored MissionBay agent configuration.
 */
final class AgentFlowCompilation {

	/**
	 * @param array<string,mixed> $flow
	 * @param array<int,string> $warnings
	 */
	public function __construct(
		private readonly array $flow,
		private readonly array $warnings = []
	) {}

	/** @return array<string,mixed> */
	public function getFlow(): array {
		return $this->flow;
	}

	/** @return array<int,string> */
	public function getWarnings(): array {
		return $this->warnings;
	}
}
