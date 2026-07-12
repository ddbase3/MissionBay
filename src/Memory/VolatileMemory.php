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

use AssistantFoundation\Api\IAgentConversationMemory;

class VolatileMemory implements IAgentConversationMemory {

	private array $nodes = [];
	private array $data = [];
	private int $max = 20;

	public static function getName(): string {
		return 'volatilememory';
	}

	public function loadNodeHistory(string $nodeId): array {
		return $this->nodes[$nodeId] ?? [];
	}

	public function appendNodeHistory(string $nodeId, array $message): void {
		$this->nodes[$nodeId][] = $message;

		if (count($this->nodes[$nodeId]) > $this->max) {
			$this->nodes[$nodeId] = array_slice($this->nodes[$nodeId], -$this->max);
		}
	}

	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
		if (!isset($this->nodes[$nodeId])) {
			return false;
		}
		foreach ($this->nodes[$nodeId] as &$entry) {
			if (($entry['id'] ?? null) === $messageId) {
				$entry['feedback'] = $feedback;
				return true;
			}
		}
		return false;
	}

	public function resetNodeHistory(string $nodeId): void {
		unset($this->nodes[$nodeId]);
	}

	public function getPriority(): int {
		return 0;
	}
}

