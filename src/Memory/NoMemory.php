<?php declare(strict_types=1);

namespace MissionBay\Memory;

use MissionBay\Api\IAgentMemory;

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

