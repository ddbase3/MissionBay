<?php declare(strict_types=1);

namespace MissionBay\Memory;

use MissionBay\Api\IAgentMemory;

class VolatileMemory implements IAgentMemory
{
	private array $nodes = [];
	private array $data = [];
	private int $max = 20;

	public static function getName(): string {
		return 'volatilememory';
	}

	public function loadNodeHistory(string $nodeId): array {
		return $this->nodes[$nodeId] ?? [];
	}

	public function appendNodeHistory(string $nodeId, string $role, string $text): void {
		$this->nodes[$nodeId][] = [$role, $text];

		if (count($this->nodes[$nodeId]) > $this->max) {
			array_shift($this->nodes[$nodeId]);
		}
	}

	public function resetNodeHistory(string $nodeId): void {
		unset($this->nodes[$nodeId]);
	}

	public function put(string $key, mixed $value): void {
		$this->data[$key] = $value;
	}

	public function get(string $key): mixed {
		return $this->data[$key] ?? null;
	}

	public function forget(string $key): void {
		unset($this->data[$key]);
	}

	public function keys(): array {
		return array_keys($this->data);
	}
}

