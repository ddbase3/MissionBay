<?php declare(strict_types=1);

namespace MissionBay\Agent;

use MissionBay\Api\IAgentRagPayloadNormalizer;
use MissionBay\Dto\AgentEmbeddingChunk;

/**
 * XrmRagPayloadNormalizer
 *
 * XRM-specific normalizer for Qdrant payloads.
 *
 * This implementation follows the NEW multi-collection normalizer interface:
 * - It is the schema owner for all collections it supports.
 * - It validates strictly (no best-effort, no guessing).
 * - It builds the final payload that will be stored.
 *
 * XRM scope:
 * - We currently support exactly one logical collectionKey: "xrm"
 * - Backend collection name is fixed here (can be changed later in ONE place).
 *
 * Required lifecycle fields (strict):
 * - metadata["content_uuid"] (string, non-empty)
 *
 * Payload rules:
 * - workflow control fields MUST NOT be persisted
 * - includes deterministic "chunktoken" derived from (hash + chunkIndex)
 */
final class XrmRagPayloadNormalizer implements IAgentRagPayloadNormalizer {

	/** @var string */
	private const COLLECTION_KEY = 'xrm';

	/** @var string physical collection name in Qdrant */
	private const BACKEND_COLLECTION = 'xrm_content_v1';

	/** @var int embedding vector size */
	private const VECTOR_SIZE = 1536;

	/** @var string Qdrant distance */
	private const DISTANCE = 'Cosine';

	public function getCollectionKeys(): array {
		return [self::COLLECTION_KEY];
	}

	public function getBackendCollectionName(string $collectionKey): string {
		$collectionKey = strtolower(trim($collectionKey));
		if ($collectionKey !== self::COLLECTION_KEY) {
			throw new \InvalidArgumentException("Unknown collectionKey '$collectionKey' for XrmRagPayloadNormalizer.");
		}
		return self::BACKEND_COLLECTION;
	}

	public function getVectorSize(string $collectionKey): int {
		$this->assertKnownCollection($collectionKey);
		return self::VECTOR_SIZE;
	}

	public function getDistance(string $collectionKey): string {
		$this->assertKnownCollection($collectionKey);
		return self::DISTANCE;
	}

	public function getSchema(string $collectionKey): array {
		$this->assertKnownCollection($collectionKey);

		return [
			// always present
			'text'           => ['type' => 'text'],
			'hash'           => ['type' => 'keyword'],
			'collection_key' => ['type' => 'keyword'],
			'content_uuid'   => ['type' => 'keyword'],
			'chunktoken'     => ['type' => 'keyword'],
			'chunk_index'    => ['type' => 'integer'],

			// optional fields (indexed if present)
			'source_id'      => ['type' => 'keyword'],
			'name'           => ['type' => 'keyword'],
			'type_alias'     => ['type' => 'keyword'],
			'content_id'     => ['type' => 'keyword'],
			'url'            => ['type' => 'keyword'],
			'filename'       => ['type' => 'keyword'],
			'lang'           => ['type' => 'keyword'],
			'created_at'     => ['type' => 'keyword'],
			'updated_at'     => ['type' => 'keyword'],
		];
	}

	public function validate(AgentEmbeddingChunk $chunk): void {
		$collectionKey = strtolower(trim((string)$chunk->collectionKey));
		if ($collectionKey === '') {
			throw new \RuntimeException('AgentEmbeddingChunk.collectionKey is required.');
		}
		if ($collectionKey !== self::COLLECTION_KEY) {
			throw new \RuntimeException("Unsupported collectionKey '$collectionKey' for XRM normalizer.");
		}

		if (!is_int($chunk->chunkIndex) || $chunk->chunkIndex < 0) {
			throw new \RuntimeException('AgentEmbeddingChunk.chunkIndex must be >= 0.');
		}

		$text = trim((string)$chunk->text);
		if ($text === '') {
			throw new \RuntimeException('AgentEmbeddingChunk.text must be non-empty for XRM.');
		}

		$hash = trim((string)$chunk->hash);
		if ($hash === '') {
			throw new \RuntimeException('AgentEmbeddingChunk.hash must be non-empty for XRM upsert.');
		}

		if (!is_array($chunk->metadata)) {
			throw new \RuntimeException('AgentEmbeddingChunk.metadata must be an array.');
		}

		$contentUuid = $chunk->metadata['content_uuid'] ?? null;
		if (!is_string($contentUuid) || trim($contentUuid) === '') {
			throw new \RuntimeException("Missing required metadata field 'content_uuid' for XRM.");
		}
	}

	public function buildPayload(AgentEmbeddingChunk $chunk): array {
		$this->validate($chunk);

		$meta = is_array($chunk->metadata) ? $chunk->metadata : [];

		$payload = [
			'text'           => trim((string)$chunk->text),
			'hash'           => trim((string)$chunk->hash),
			'collection_key' => self::COLLECTION_KEY,
			'content_uuid'   => $this->asUpperHex($meta['content_uuid'] ?? null),
			'chunktoken'     => $this->buildChunkToken($chunk->hash, $chunk->chunkIndex),
			'chunk_index'    => $chunk->chunkIndex,
		];

		// Optional fields (kept minimal)
		$this->addIfString($payload, 'source_id', $meta['source_id'] ?? null);
		$this->addIfString($payload, 'name', $meta['name'] ?? null);
		$this->addIfString($payload, 'type_alias', $meta['type_alias'] ?? null);
		$this->addIfString($payload, 'content_id', $meta['content_id'] ?? null);
		$this->addIfString($payload, 'url', $meta['url'] ?? null);
		$this->addIfString($payload, 'filename', $meta['filename'] ?? null);
		$this->addIfString($payload, 'lang', $meta['lang'] ?? null);
		$this->addIfString($payload, 'created_at', $meta['created_at'] ?? null);
		$this->addIfString($payload, 'updated_at', $meta['updated_at'] ?? null);

		// Unknown domain metadata is preserved as extra (but operational keys are filtered out)
		$extra = $this->collectExtra($meta, [
			'content_uuid',
			'hash',
			'chunk_index',
			'chunktoken',
			'collection_key',

			'source_id',
			'name',
			'type_alias',
			'content_id',
			'url',
			'filename',
			'lang',
			'created_at',
			'updated_at'
		]);

		if (!empty($extra)) {
			$payload['extra'] = $extra;
		}

		return $payload;
	}

	// ---------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------

	private function assertKnownCollection(string $collectionKey): void {
		$collectionKey = strtolower(trim($collectionKey));
		if ($collectionKey !== self::COLLECTION_KEY) {
			throw new \InvalidArgumentException("Unknown collectionKey '$collectionKey'.");
		}
	}

	private function buildChunkToken(string $hash, int $chunkIndex): string {
		$hash = trim($hash);
		if ($hash === '') {
			// validate() already enforces non-empty hash, so this is defensive only.
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

	private function asUpperHex(mixed $v): string {
		$s = $this->asString($v);
		if ($s === null) {
			return '';
		}

		$s = trim($s);
		if ($s === '') {
			return '';
		}

		$s = preg_replace('/[^0-9a-fA-F]/', '', $s);
		if ($s === null || $s === '') {
			return '';
		}

		return strtoupper($s);
	}

	private function addIfString(array &$payload, string $key, mixed $value): void {
		$s = $this->asString($value);
		if ($s === null) return;

		$s = trim($s);
		if ($s === '') return;

		$payload[$key] = $s;
	}

	private function collectExtra(array $metadata, array $knownKeys): array {
		$extra = [];

		foreach ($metadata as $k => $v) {
			if (in_array($k, $knownKeys, true)) {
				continue;
			}

			// Never persist workflow/queue control fields (strict rule)
			if (in_array($k, ['job_id', 'attempts', 'locked_until', 'claim_token', 'claimed_at', 'state', 'error_message'], true)) {
				continue;
			}

			// Also never persist routing/control fields (they are first-class elsewhere)
			if (in_array($k, ['action', 'collectionKey', 'collection_key'], true)) {
				continue;
			}

			$extra[$k] = $v;
		}

		return $extra;
	}
}
