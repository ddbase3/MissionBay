<?php declare(strict_types=1);

namespace MissionBay\Api;

use Base3\Api\IBase;

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
}

