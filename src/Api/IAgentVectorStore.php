<?php declare(strict_types=1);

namespace MissionBay\Api;

use MissionBay\Dto\AgentEmbeddingChunk;

/**
 * IAgentVectorStore
 *
 * Unified Vector Store contract for RAG storage and retrieval.
 *
 * Core responsibilities:
 * - Upsert vectors + payload into a target collection
 * - Duplicate checks (typically via hash)
 * - Delete by filter (for replace/delete workflows)
 * - Similarity search
 * - Collection lifecycle helpers (create/delete/info)
 *
 * Design rules (important, no interpretation):
 * - The VectorStore does NOT decide routing.
 *   Routing is decided upstream by the extractor and carried as `collectionKey`.
 * - The VectorStore must be able to operate on multiple collections.
 *   Therefore, collection selection is always driven by `AgentEmbeddingChunk::$collectionKey`
 *   and collection definitions are provided by the normalizer layer (not by flow config).
 * - Metadata passed into the store is domain metadata only.
 *   Queue/workflow control fields must not be persisted.
 */
interface IAgentVectorStore {

	/**
	 * Upserts a single embedding chunk into its target collection.
	 *
	 * The target is determined exclusively by:
	 * - $chunk->collectionKey
	 *
	 * The chunk must contain:
	 * - text (non-empty)
	 * - vector (non-empty)
	 * - hash (stable for upsert workflows)
	 * - chunkIndex (0..n)
	 * - metadata (domain keys used for lifecycle, e.g. content_uuid)
	 *
	 * Implementations may ignore any internal id concept and generate their own UUIDs.
	 */
	public function upsert(AgentEmbeddingChunk $chunk): void;

	/**
	 * Checks whether a given hash already exists in the target collection.
	 *
	 * This is used for cheap duplicate detection in "skip" mode.
	 *
	 * @param string $collectionKey
	 * @param string $hash
	 */
	public function existsByHash(string $collectionKey, string $hash): bool;

	/**
	 * Checks whether an entry exists by a simple metadata filter in the target collection.
	 *
	 * Filter format:
	 * - ['content_uuid' => '...']
	 * - ['hash' => '...']
	 * - ['content_uuid' => ['a','b']]  (OR on same key)
	 *
	 * @param string $collectionKey
	 * @param array<string,mixed> $filter
	 */
	public function existsByFilter(string $collectionKey, array $filter): bool;

	/**
	 * Deletes points by a simple metadata filter in the target collection.
	 *
	 * Returns the number of deleted points if the backend provides it,
	 * otherwise returns 0.
	 *
	 * @param string $collectionKey
	 * @param array<string,mixed> $filter
	 */
	public function deleteByFilter(string $collectionKey, array $filter): int;

	/**
	 * Vector similarity search in the target collection.
	 *
	 * Returned items contain:
	 * - 'id' (UUID string)
	 * - 'score'
	 * - 'payload'
	 *
	 * @param string $collectionKey
	 * @param array<float> $vector
	 * @param int $limit
	 * @param float|null $minScore
	 * @return array<int,array<string,mixed>>
	 */
	public function search(string $collectionKey, array $vector, int $limit = 3, ?float $minScore = null): array;

	/**
	 * Creates the underlying collection / index for the given collectionKey if needed.
	 *
	 * Note:
	 * - The physical collection name and schema are resolved by the VectorStore's
	 *   internal configuration via the normalizer layer.
	 */
	public function createCollection(string $collectionKey): void;

	/**
	 * Drops the underlying collection / index for the given collectionKey.
	 */
	public function deleteCollection(string $collectionKey): void;

	/**
	 * Returns collection metadata for the given collectionKey.
	 *
	 * @return array<string,mixed>
	 */
	public function getInfo(string $collectionKey): array;
}
