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

namespace MissionBay\Orchestrator\Suspension;

use AssistantFoundation\Api\IAgentSuspensionRepository;
use AssistantFoundation\Dto\AgentSuspension;
use AssistantFoundation\Dto\AgentSuspensionClaim;
use AssistantFoundation\Exception\AgentSuspensionRepositoryException;

/** Fails closed when a project has not configured persistent runtime state. */
final class UnavailableAgentSuspensionRepository implements IAgentSuspensionRepository {

	public function create(AgentSuspension $suspension, int $ttlSeconds): string {
		throw $this->unavailable();
	}

	public function claim(string $resumeHandle): AgentSuspensionClaim {
		throw $this->unavailable();
	}

	public function release(AgentSuspensionClaim $claim): void {
	}

	public function consume(AgentSuspensionClaim $claim): void {
		throw $this->unavailable();
	}

	private function unavailable(): AgentSuspensionRepositoryException {
		return new AgentSuspensionRepositoryException(
			AgentSuspensionRepositoryException::REASON_UNAVAILABLE,
			'Agent suspension persistence requires an IStateStore implementation.'
		);
	}
}
