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

namespace MissionBay\Memory;

use AssistantFoundation\Api\IAgentMemory;

class NoMemory implements IAgentMemory
{
	public static function getName(): string {
		return 'nomemory';
	}

	public function loadNodeHistory(string $nodeId): array {
		return [];
	}

	public function appendNodeHistory(string $nodeId, array $message): void {
		// intentionally stateless
	}

	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
		return false;
	}

	public function resetNodeHistory(string $nodeId): void {
		// nothing to clear
	}

	public function getPriority(): int {
		return 0;
	}
}

