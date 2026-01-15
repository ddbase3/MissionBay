<?php declare(strict_types=1);

namespace MissionBay\Agent;

use MissionBay\Api\IAgentRagPayloadNormalizer;
use MissionBay\Dto\AgentEmbeddingChunk;

/**
 * AgentRagPayloadNormalizer
 *
 * XRM normalizer for Qdrant payloads.
 *
 * Contract goals:
 * - Strict validation (no guessing, no best-effort).
 * - Multi-collection contract, but XRM currently collapses all incoming domain keys
 *   (file/contact/address/...) into ONE logical collection: "xrm".
 * - VectorStore stays dumb: it calls normalizer to validate/build payload and asks for backend collection name.
 *
 * XRM current routing:
 * - Any incoming chunk.collectionKey is accepted (non-empty)
 * - It is mapped to canonical "xrm" for storage semantics
 * - Backend collection is fixed: "xrm_content_v1"
 */
final class AgentRagPayloadNormalizer implements IAgentRagPayloadNormalizer {

	/** @var string canonical logical collection key for XRM */
	private const CANONICAL_COLLECTION_KEY = 'xrm';

	/** @var string physical backend collection name in Qdrant */
	private const BACKEND_COLLECTION = 'xrm_content_v1';

	/** @var int embedding vector size */
	private const VECTOR_SIZE = 1536;

	/** @var string Qdrant distance */
	private const DISTANCE = 'Cosine';

	/**
	 * IMPORTANT:
	 * - Default OFF: don't spam CLI output unless explicitly enabled.
	 * - Can be flipped via setDebug(true) from your CLI runner/bootstrap if you want.
	 */
	private bool $debug = false;

	public function setDebug(bool $debug): void {
		$this->debug = $debug;
	}

	public function getCollectionKeys(): array {
		// Canonical keys owned by this normalizer.
		return [self::CANONICAL_COLLECTION_KEY];
	}

	public function getBackendCollectionName(string $collectionKey): string {
		$this->mapToCanonicalCollectionKey($collectionKey);
		return self::BACKEND_COLLECTION;
	}

	public function getVectorSize(string $collectionKey): int {
		$this->mapToCanonicalCollectionKey($collectionKey);
		return self::VECTOR_SIZE;
	}

	public function getDistance(string $collectionKey): string {
		$this->mapToCanonicalCollectionKey($collectionKey);
		return self::DISTANCE;
	}

	public function getSchema(string $collectionKey): array {
		$this->mapToCanonicalCollectionKey($collectionKey);

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
		$incomingKey = trim((string)$chunk->collectionKey);
		if ($incomingKey === '') {
			throw new \RuntimeException('AgentEmbeddingChunk.collectionKey is required.');
		}

		// XRM: accept any incoming key, but enforce canonical storage semantics.
		$canonical = $this->mapToCanonicalCollectionKey($incomingKey);

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

		// Normalize the DTO for downstream consistency (store/delete/exists).
		// This ensures the VectorStore "sees" the canonical key even if upstream used "file", "contact", etc.
		$chunk->collectionKey = $canonical;
	}

	public function buildPayload(AgentEmbeddingChunk $chunk): array {
		// validate() canonicalizes $chunk->collectionKey for us.
		$this->validate($chunk);

		$meta = is_array($chunk->metadata) ? $chunk->metadata : [];

		$payload = [
			'text'           => trim((string)$chunk->text),
			'hash'           => trim((string)$chunk->hash),
			'collection_key' => self::CANONICAL_COLLECTION_KEY,
			'content_uuid'   => $this->asUpperHex($meta['content_uuid'] ?? null),
			'chunktoken'     => $this->buildChunkToken((string)$chunk->hash, (int)$chunk->chunkIndex),
			'chunk_index'    => (int)$chunk->chunkIndex,
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
	// Routing
	// ---------------------------------------------------------

	private function mapToCanonicalCollectionKey(string $incomingKey): string {
		$incomingKey = strtolower(trim($incomingKey));
		if ($incomingKey === '') {
			throw new \InvalidArgumentException("collectionKey must be non-empty for XRM normalizer.");
		}

		// XRM current mode: everything goes into the single canonical collection.
		if ($incomingKey !== self::CANONICAL_COLLECTION_KEY) {
			$this->debugLog("Mapping incoming collectionKey '{$incomingKey}' -> '" . self::CANONICAL_COLLECTION_KEY . "'");
		}

		return self::CANONICAL_COLLECTION_KEY;
	}

	private function debugLog(string $msg): void {
		if (!$this->debug) {
			return;
		}
		echo "[AgentRagPayloadNormalizer] {$msg}\n";
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
