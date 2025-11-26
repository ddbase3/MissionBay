<?php declare(strict_types=1);

namespace MissionBay\Api;

/**
 * Normalizes arbitrary metadata into a unified RAG payload structure.
 *
 * Responsible for:
 * - building a flat payload array (text, hash, content_id, path, etc.)
 * - optionally exposing a logical schema for collection/index creation
 */
interface IAgentRagPayloadNormalizer {

	/**
	 * Build a normalized payload for a vector store entry.
	 *
	 * @param string $text
	 * @param string $hash
	 * @param array<string,mixed> $metadata
	 * @return array<string,mixed>
	 */
	public function normalize(string $text, string $hash, array $metadata = []): array;

	/**
	 * Returns a logical payload schema description.
	 * Can be used by vector stores when creating collections/indexes.
	 *
	 * @return array<string,mixed>
	 */
	public function getSchema(): array;
}
