<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentVectorStore;

/**
 * NoVectorStoreAgentResource
 *
 * A no-operation vector store resource.
 * Does not store, search, or return any data.
 */
class NoVectorStoreAgentResource extends AbstractAgentResource implements IAgentVectorStore {

	/**
	 * Returns the internal name of this resource.
	 */
	public static function getName(): string {
		return 'novectorstoreagentresource';
	}

	/**
	 * Returns a human-readable description.
	 */
	public function getDescription(): string {
		return 'A no-operation vector store that does not store or retrieve vectors.';
	}

	/**
	 * No-op: does not store anything.
	 */
	public function upsert(string $id, array $vector, string $text, string $hash, array $metadata = []): void {
		// Intentionally left blank
	}

	/**
	 * No-op: always returns false.
	 */
	public function existsByHash(string $hash): bool {
		return false;
	}

	/**
	 * No-op: always returns an empty result list.
	 */
	public function search(array $vector, int $limit = 3, ?float $minScore = null): array {
		return [];
	}

	/**
	 * No-op: nothing to create.
	 */
	public function createCollection(): void {
		// No collection to create
	}

	/**
	 * No-op: nothing to delete.
	 */
	public function deleteCollection(): void {
		// No collection to delete
	}

	/**
	 * Returns static info describing the no-operation nature.
	 */
	public function getInfo(): array {
		return [
			'type'       => 'no-op',
			'collection' => null,
			'count'      => 0,
			'ids'        => [],
			'details'    => [
				'persistent'  => false,
				'description' => 'This vector store does not store or return any data.'
			]
		];
	}
}
