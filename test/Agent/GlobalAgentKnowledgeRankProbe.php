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

namespace Base3\MissionBay\Test\Agent;

use Base3\MissionBay\Agent\GlobalAgentKnowledgeService;

final class GlobalAgentKnowledgeRankProbe extends GlobalAgentKnowledgeService {

	public function shouldReplace(array $existing, array $candidate): bool {
		return $this->shouldOverrideMergedEntry($existing, $candidate);
	}

	public function canReadGlobal(array $entry): bool {
		return $this->canAccessEntry($entry);
	}
}
