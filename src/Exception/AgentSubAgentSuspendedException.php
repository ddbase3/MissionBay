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

namespace Base3\MissionBay\Exception;

use Base3\AssistantFoundation\Dto\AgentSuspensionState;

/** Signals a child-agent suspension to the owning MissionBay tool execution stage. */
final class AgentSubAgentSuspendedException extends \RuntimeException {

	public function __construct(
		private readonly AgentSuspensionState $suspension,
		private readonly string $expiresAt = ''
	) {
		if (!$suspension->isSuspended() || trim($suspension->getResumeHandle()) === '' || $suspension->getInteractionRequests() === []) {
			throw new \InvalidArgumentException('Delegated suspension requires a resumable interaction.');
		}
		parent::__construct('A delegated agent is awaiting user interaction.');
	}

	public function getSuspension(): AgentSuspensionState {
		return $this->suspension;
	}

	public function getExpiresAt(): string {
		return $this->expiresAt;
	}
}
