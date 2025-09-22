<?php declare(strict_types=1);

namespace MissionBay\Api;

use Base3\Api\IBase;

/**
 * Interface for storing and retrieving memory during agent execution.
 * Supports chat history per node and global key-value data.
 */
interface IAgentMemory extends IBase {

	/**
	 * Returns the chat history associated with a specific node.
	 *
	 * @param string $nodeId ID of the node.
	 * @return array Array of messages, typically including role and text.
	 */
	public function loadNodeHistory(string $nodeId): array;

	/**
	 * Appends a message to the chat history of a given node.
	 *
	 * @param string $nodeId ID of the node.
	 * @param string $role Role of the message sender (e.g. 'user', 'assistant').
	 * @param string $text Message content.
	 */
	public function appendNodeHistory(string $nodeId, string $role, string $text): void;

	/**
	 * Clears the chat history for a specific node.
	 *
	 * @param string $nodeId ID of the node to reset.
	 */
	public function resetNodeHistory(string $nodeId): void;

	/**
	 * Stores a key-value pair in global memory.
	 *
	 * @param string $key Unique key identifier.
	 * @param mixed $value Arbitrary value to store.
	 */
	public function put(string $key, mixed $value): void;

	/**
	 * Retrieves a value from global memory by key.
	 *
	 * @param string $key Key identifier.
	 * @return mixed Stored value, or null if not found.
	 */
	public function get(string $key): mixed;

	/**
	 * Deletes a key-value pair from global memory.
	 *
	 * @param string $key Key to remove.
	 */
	public function forget(string $key): void;

	/**
	 * Returns a list of all currently stored keys.
	 *
	 * @return array List of key names.
	 */
	public function keys(): array;

	/**
	 * Defines the priority for this memory instance.
	 *
	 * Lower numbers indicate higher priority (executed earlier).
	 *
	 * @return int
	 */
	public function getPriority(): int;
}

