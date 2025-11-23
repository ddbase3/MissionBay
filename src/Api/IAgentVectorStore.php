<?php declare(strict_types=1);

namespace MissionBay\Api;

/**
 * Unified Vector Store contract:
 * - insert/upsert embeddings (ID parameter is ignored)
 * - search vectors
 * - duplicate detection via content-hash
 * - lifecycle management for collections/indexes
 */
interface IAgentVectorStore {

	/**
	 * Upserts a vector with payload.
	 *
	 * Important:
	 * The `$id` parameter is ignored by all implementations.
	 * Vector stores generate their own UUID v4 identifiers internally.
	 *
	 * @param string $id Ignored by implementations.
	 * @param array<float> $vector
	 * @param string $text
	 * @param string $hash
	 * @param array<string,mixed> $metadata
	 */
	public function upsert(string $id, array $vector, string $text, string $hash, array $metadata = []): void;

	/**
	 * Checks whether a given content hash already exists.
	 */
	public function existsByHash(string $hash): bool;

	/**
	 * Vector similarity search.
	 *
	 * Returned items contain:
	 * - 'id' (UUID string)
	 * - 'score'
	 * - 'payload'
	 *
	 * @param array<float> $vector
	 * @param int $limit
	 * @param float|null $minScore
	 * @return array<int,array<string,mixed>>
	 */
	public function search(array $vector, int $limit = 3, ?float $minScore = null): array;

	/**
	 * Creates the underlying collection / index if needed.
	 */
	public function createCollection(): void;

	/**
	 * Drops the underlying collection / index.
	 */
	public function deleteCollection(): void;

	/**
	 * Returns collection metadata.
	 *
	 * @return array<string,mixed>
	 */
	public function getInfo(): array;
}
