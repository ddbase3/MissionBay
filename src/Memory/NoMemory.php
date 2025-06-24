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

	public function appendNodeHistory(string $nodeId, string $role, string $text): void {
		// intentionally stateless
	}

	public function resetNodeHistory(string $nodeId): void {
		// nothing to clear
	}

	public function put(string $key, mixed $value): void {
		// no-op
	}

	public function get(string $key): mixed {
		return null;
	}

	public function forget(string $key): void {
		// no-op
	}

	public function keys(): array {
		return [];
	}
}

