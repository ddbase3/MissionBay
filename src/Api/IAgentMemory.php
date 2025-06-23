<?php declare(strict_types=1);

namespace MissionBay\Api;

use Base3\Api\IBase;

/**
 * Interface for storing and managing memory entries per user in agent flows.
 */
interface IAgentMemory extends IBase {

	/**
	 * Loads the memory history for a given user.
	 *
	 * @param string $userId
	 * @return array Array of [role, message] entries
	 */
	public function load(string $userId): array;

	/**
	 * Appends a message with role to the user's memory.
	 *
	 * @param string $userId
	 * @param string $role For example "user" or "bot"
	 * @param string $text The actual message content
	 */
	public function remember(string $userId, string $role, string $text): void;

	/**
	 * Resets (clears) all memory entries for the given user.
	 *
	 * @param string $userId
	 */
	public function reset(string $userId): void;
}

