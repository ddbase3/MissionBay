<?php declare(strict_types=1);

namespace MissionBay\Memory;

use MissionBay\Api\IAgentMemory;

class NoMemory implements IAgentMemory {

	public static function getName(): string {
		return 'nomemory';
	}

	public function load(string $userId): array {
		return [];
	}

	public function remember(string $userId, string $role, string $text): void {
		// No memory storage performed
	}

	public function reset(string $userId): void {
		// Nothing to clear
	}
}

