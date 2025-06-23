<?php declare(strict_types=1);

namespace MissionBay\Memory;

use MissionBay\Api\IAgentMemory;

class VolatileMemory implements IAgentMemory {

	private array $memory = [];
	private int $max = 5;

	public static function getName(): string {
		return 'volatilememory';
	}

	/**
	 * Loads the message history for a given user ID.
	 */
	public function load(string $userId): array {
		return $this->memory[$userId] ?? [];
	}

	/**
	 * Appends a new message entry to the memory.
	 */
	public function remember(string $userId, string $role, string $text): void {
		$this->memory[$userId][] = [$role, $text];

		if (count($this->memory[$userId]) > $this->max) {
			array_shift($this->memory[$userId]);
		}
	}

	/**
	 * Clears the message history for the given user ID.
	 */
	public function reset(string $userId): void {
		unset($this->memory[$userId]);
	}
}

