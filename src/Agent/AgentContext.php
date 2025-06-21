<?php declare(strict_types=1);

namespace MissionBay\Agent;

use MissionBay\Api\IAgentMemory;

class AgentContext {

	private IAgentMemory $memory;

	public function __construct(IAgentMemory $memory) {
		$this->memory = $memory;
	}

	/**
	 * Load the full message history for a given user ID.
	 *
	 * @param string $userId
	 * @return array
	 */
	public function loadHistory(string $userId): array {
		return $this->memory->load($userId);
	}

	/**
	 * Add a message with a specific role to the user’s memory.
	 *
	 * @param string $userId
	 * @param string $role e.g. "user" or "bot"
	 * @param string $msg
	 */
	public function remember(string $userId, string $role, string $msg): void {
		$this->memory->remember($userId, $role, $msg);
	}

	/**
	 * Access to the underlying memory implementation.
	 *
	 * @return IAgentMemory
	 */
	public function getMemory(): IAgentMemory {
		return $this->memory;
	}
}

