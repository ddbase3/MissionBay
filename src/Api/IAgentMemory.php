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
	 * @return array Array of message objects (each typically including id, role, content, timestamp, feedback).
	 */
	public function loadNodeHistory(string $nodeId): array;

	/**
	 * Appends a complete message object to the chat history of a given node.
	 *
	 * @param string $nodeId  ID of the node.
	 * @param array  $message Message object containing at least:
	 *                        - id        (string) Unique message ID
	 *                        - role      (string) Sender role, e.g. 'user' or 'assistant'
	 *                        - content   (string) Message content
	 *                        - timestamp (string) ISO 8601 timestamp
	 *                        - feedback  (?string) Optional feedback
	 */
	public function appendNodeHistory(string $nodeId, array $message): void;

	/**
	 * Sets or clears feedback for a specific message in a node's history.
	 *
	 * @param string  $nodeId    ID of the node.
	 * @param string  $messageId ID of the message within the node's history.
	 * @param ?string $feedback  Text feedback, or null to reset.
	 * @return bool True if the message was found and updated, false otherwise.
	 */
	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool;

	/**
	 * Clears the chat history for a specific node.
	 *
	 * @param string $nodeId ID of the node to reset.
	 */
	public function resetNodeHistory(string $nodeId): void;

	/**
	 * Stores a key-value pair in global memory.
	 *
	 * @param string $key   Unique key identifier.
	 * @param mixed  $value Arbitrary value to store.
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

