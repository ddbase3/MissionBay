<?php declare(strict_types=1);

namespace MissionBay\Agent;

use MissionBay\Api\IAgentRagPayloadNormalizer;
use MissionBay\Dto\AgentEmbeddingChunk;

/**
 * AgentRagPayloadNormalizer
 *
 * Generic payload normalizer for vector stores.
 *
 * Minimal policy:
 * - Strict validation (no guessing, no silent fallback)
 * - Multi-collection support via an in-class config map
 * - Preserve unknown metadata as payload.meta
 * - Exclude workflow/queue control fields from payload
 */
final class AgentRagPayloadNormalizer implements IAgentRagPayloadNormalizer {

	/**
	 * Collection configuration:
	 * - backend: string
	 * - vector_size: int
	 * - distance: string
	 * - schema: array<string,mixed>
	 * - required_meta: string[]
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private array $collections = [];

	/** @var string[] */
	private array $workflowKeys = [
		'job_id',
		'attempts',
		'locked_until',
		'claim_token',
		'claimed_at',
		'state',
		'error_message'
	];

	/** @var string[] */
	private array $internalKeys = [
		'action',
		'collectionKey',
		'collection_key'
	];

	public function __construct(?array $collections = null) {
		$this->collections = $collections ?? $this->buildDefaultCollections();
	}

	/**
	 * @param array<string,array<string,mixed>> $collections
	 */
	public function setCollections(array $collections): void {
		$this->collections = $collections;
	}

	public function getCollectionKeys(): array {
		return array_keys($this->collections);
	}

	public function getBackendCollectionName(string $collectionKey): string {
		$cfg = $this->getCollectionConfig($collectionKey);
		return (string)$cfg['backend'];
	}

	public function getVectorSize(string $collectionKey): int {
		$cfg = $this->getCollectionConfig($collectionKey);
		return (int)$cfg['vector_size'];
	}

	public function getDistance(string $collectionKey): string {
		$cfg = $this->getCollectionConfig($collectionKey);
		return (string)$cfg['distance'];
	}

	public function getSchema(string $collectionKey): array {
		$cfg = $this->getCollectionConfig($collectionKey);
		return is_array($cfg['schema'] ?? null) ? $cfg['schema'] : [];
	}

	public function validate(AgentEmbeddingChunk $chunk): void {
		$collectionKey = trim((string)$chunk->collectionKey);
		if ($collectionKey === '') {
			throw new \RuntimeException('AgentEmbeddingChunk.collectionKey is required.');
		}

		$cfg = $this->getCollectionConfig($collectionKey);

		if (!is_int($chunk->chunkIndex) || $chunk->chunkIndex < 0) {
			throw new \RuntimeException('AgentEmbeddingChunk.chunkIndex must be >= 0.');
		}

		$text = trim((string)$chunk->text);
		if ($text === '') {
			throw new \RuntimeException('AgentEmbeddingChunk.text must be non-empty.');
		}

		$hash = trim((string)$chunk->hash);
		if ($hash === '') {
			throw new \RuntimeException('AgentEmbeddingChunk.hash must be non-empty.');
		}

		if (!is_array($chunk->metadata)) {
			throw new \RuntimeException('AgentEmbeddingChunk.metadata must be an array.');
		}

		$required = $this->asStringArray($cfg['required_meta'] ?? []);
		foreach ($required as $k) {
			$v = $chunk->metadata[$k] ?? null;
			$s = $this->asString($v);
			if ($s === null || trim($s) === '') {
				throw new \RuntimeException("Missing required metadata field '{$k}'.");
			}
		}
	}

	public function buildPayload(AgentEmbeddingChunk $chunk): array {
		$this->validate($chunk);

		$meta = is_array($chunk->metadata) ? $chunk->metadata : [];
		$collectionKey = trim((string)$chunk->collectionKey);

		$payload = [
			'text' => trim((string)$chunk->text),
			'hash' => trim((string)$chunk->hash),
			'collection_key' => $collectionKey,
			'chunktoken' => $this->buildChunkToken((string)$chunk->hash, (int)$chunk->chunkIndex),
			'chunk_index' => (int)$chunk->chunkIndex
		];

		$this->addIfString($payload, 'content_uuid', $meta['content_uuid'] ?? null);

		$known = [
			'content_uuid'
		];

		$metaOut = $this->collectMeta($meta, $known);
		if (!empty($metaOut)) {
			$payload['meta'] = $metaOut;
		}

		return $payload;
	}

	// ---------------------------------------------------------
	// Collections / Defaults
	// ---------------------------------------------------------

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function buildDefaultCollections(): array {
		return [
			'default' => [
				'backend' => 'content_v1',
				'vector_size' => 1536,
				'distance' => 'Cosine',
				'required_meta' => ['content_uuid'],
				'schema' => $this->buildDefaultSchema()
			]
		];
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function buildDefaultSchema(): array {
		return [
			'text' => ['type' => 'text', 'index' => false],
			'hash' => ['type' => 'keyword', 'index' => true],
			'collection_key' => ['type' => 'keyword', 'index' => true],
			'content_uuid' => ['type' => 'keyword', 'index' => true],
			'chunktoken' => ['type' => 'keyword', 'index' => true],
			'chunk_index' => ['type' => 'integer', 'index' => true],

			'meta' => ['type' => 'object', 'index' => false]
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function getCollectionConfig(string $collectionKey): array {
		$key = trim((string)$collectionKey);
		if ($key === '') {
			throw new \InvalidArgumentException('collectionKey must be non-empty.');
		}

		if (!isset($this->collections[$key]) || !is_array($this->collections[$key])) {
			throw new \InvalidArgumentException("Unknown collectionKey '{$key}'.");
		}

		return $this->collections[$key];
	}

	// ---------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------

	private function buildChunkToken(string $hash, int $chunkIndex): string {
		$hash = trim($hash);
		if ($hash === '') {
			throw new \RuntimeException('Cannot build chunktoken: hash is empty.');
		}
		return $chunkIndex > 0 ? ($hash . '-' . $chunkIndex) : $hash;
	}

	private function asString(mixed $v): ?string {
		if ($v === null) return null;
		if (is_string($v)) return $v;
		if (is_numeric($v) || is_bool($v)) return (string)$v;
		return null;
	}

	/**
	 * @return string[]
	 */
	private function asStringArray(mixed $v): array {
		if (!is_array($v)) return [];
		$out = [];
		foreach ($v as $x) {
			$s = $this->asString($x);
			if ($s === null) continue;
			$s = trim($s);
			if ($s === '') continue;
			$out[] = $s;
		}
		return $out;
	}

	private function addIfString(array &$payload, string $key, mixed $value): void {
		$s = $this->asString($value);
		if ($s === null) return;

		$s = trim($s);
		if ($s === '') return;

		$payload[$key] = $s;
	}

	/**
	 * @param array<string,mixed> $metadata
	 * @param string[] $knownKeys
	 * @return array<string,mixed>
	 */
	private function collectMeta(array $metadata, array $knownKeys): array {
		$out = [];

		foreach ($metadata as $k => $v) {
			if (in_array($k, $knownKeys, true)) {
				continue;
			}
			if (in_array($k, $this->workflowKeys, true)) {
				continue;
			}
			if (in_array($k, $this->internalKeys, true)) {
				continue;
			}

			$out[$k] = $v;
		}

		return $out;
	}
}
