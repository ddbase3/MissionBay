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

use AssistantFoundation\Dto\AgentResume;
use AssistantFoundation\Dto\AgentSuspension;
use AssistantFoundation\Dto\AgentSuspensionClaim;

/** Internal resume data after the opaque handle has been claimed server-side. */
final class PreparedAgentResume {

	public function __construct(
		private readonly AgentResume $resume,
		private readonly AgentSuspensionClaim $claim
	) {
		if (!hash_equals($resume->getResumeHandle(), $claim->getResumeHandle())) {
			throw new \InvalidArgumentException('Prepared resume and suspension claim handles do not match.');
		}
	}

	public function getResume(): AgentResume {
		return $this->resume;
	}

	public function getClaim(): AgentSuspensionClaim {
		return $this->claim;
	}

	public function getResumeHandle(): string {
		return $this->claim->getResumeHandle();
	}

	public function getSuspension(): AgentSuspension {
		return $this->claim->getSuspension();
	}
}
