<?php declare(strict_types=1);

namespace MissionBay\Memory;

use MissionBay\Api\IAgentMemory;

class VolatileMemory implements IAgentMemory {

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

