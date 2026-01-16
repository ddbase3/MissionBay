<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentVectorStore;
use MissionBay\Dto\AgentEmbeddingChunk;

/**
 * NoVectorStoreAgentResource
 *
 * A no-operation vector store resource.
 * Does not store, search, or return any data.
 *
 * Implements IAgentVectorStore (multi-collection contract), but intentionally does nothing.
 */
final class NoVectorStoreAgentResource extends AbstractAgentResource implements IAgentVectorStore {

	public static function getName(): string {
		return 'novectorstoreagentresource';
	}

	public function getDescription(): string {
		return 'A no-operation vector store that does not store or retrieve vectors.';
	}

	public function upsert(AgentEmbeddingChunk $chunk): void {
		// Intentionally no-op
	}

	public function existsByHash(string $collectionKey, string $hash): bool {
		return false;
	}

	public function existsByFilter(string $collectionKey, array $filter): bool {
		return false;
	}

	public function deleteByFilter(string $collectionKey, array $filter): int {
		return 0;
	}

	public function search(
		string $collectionKey,
		array $vector,
		int $limit = 3,
		?float $minScore = null,
		?array $filterSpec = null
	): array {
		return [];
	}

	public function createCollection(string $collectionKey): void {
		// Intentionally no-op
	}

	public function deleteCollection(string $collectionKey): void {
		// Intentionally no-op
	}

	public function getInfo(string $collectionKey): array {
		return [
			'type' => 'no-op',
			'collection_key' => $collectionKey,
			'collection' => null,
			'count' => 0,
			'details' => [
				'persistent' => false,
				'description' => 'This vector store does not store or return any data.'
			]
		];
	}
}
