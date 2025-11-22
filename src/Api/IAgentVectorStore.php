<?php declare(strict_types=1);

namespace MissionBay\Api;

/**
 * Unified Vector Store contract:
 * - insert/upsert embeddings
 * - search vectors
 * - duplicate detection via content-hash
 */
interface IAgentVectorStore {

	/**
	 * Upserts a vector with metadata.
	 *
	 * @param string $id
	 * @param array<float> $vector
	 * @param array<string,mixed> $metadata
	 */
	public function upsert(string $id, array $vector, array $metadata = []): void;

	/**
	 * Checks whether a given content hash already exists.
	 */
	public function existsByHash(string $hash): bool;

	/**
	 * Vector similarity search.
	 */
	public function search(array $vector, int $limit = 3, ?float $minScore = null): array;
}
