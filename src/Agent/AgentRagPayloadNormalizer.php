<?php declare(strict_types=1);

namespace MissionBay\Agent;

use MissionBay\Api\IAgentRagPayloadNormalizer;
use MissionBay\Dto\AgentEmbeddingChunk;

/**
 * AgentRagPayloadNormalizer
 *
 * XRM normalizer for Qdrant payloads.
 *
 * Adds filterable fields:
 * - tags: array<string>
 * - ref_uuids: array<string> (normalized to upper hex, like content_uuid)
 * - num_chunks: int (same value for all chunks of one content item)
 * - archive: int (0|1) to filter archived entries
 * - public: int (0|1) to filter public entries
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

			// optional convenience metadata
			'num_chunks'     => ['type' => 'integer'],
			'archive'        => ['type' => 'integer'],
			'public'         => ['type' => 'integer'],

			// filterable arrays
			'tags'           => ['type' => 'keyword'],
			'ref_uuids'      => ['type' => 'keyword'],
		];
	}

	public function validate(AgentEmbeddingChunk $chunk): void {
		$incomingKey = trim((string)$chunk->collectionKey);
		if ($incomingKey === '') {
			throw new \RuntimeException('AgentEmbeddingChunk.collectionKey is required.');
		}

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

		// Strict optional arrays
		if (array_key_exists('tags', $chunk->metadata) && !is_array($chunk->metadata['tags'])) {
			throw new \RuntimeException("metadata field 'tags' must be an array if provided.");
		}
		if (array_key_exists('ref_uuids', $chunk->metadata) && !is_array($chunk->metadata['ref_uuids'])) {
			throw new \RuntimeException("metadata field 'ref_uuids' must be an array if provided.");
		}

		// Strict optional int
		if (array_key_exists('num_chunks', $chunk->metadata)) {
			$n = $chunk->metadata['num_chunks'];

			if (!(is_int($n) || (is_string($n) && ctype_digit($n)))) {
				throw new \RuntimeException("metadata field 'num_chunks' must be an integer if provided.");
			}

			$n = (int)$n;
			if ($n <= 0) {
				throw new \RuntimeException("metadata field 'num_chunks' must be > 0 if provided.");
			}
		}

		// Strict optional boolean-int (0|1)
		$this->assertBoolIntMeta($chunk->metadata, 'archive');
		$this->assertBoolIntMeta($chunk->metadata, 'public');

		// Canonicalize key for downstream consistency (store/delete/exists).
		$chunk->collectionKey = $canonical;
	}

	public function buildPayload(AgentEmbeddingChunk $chunk): array {
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

		// Optional scalar fields
		$this->addIfString($payload, 'source_id', $meta['source_id'] ?? null);
		$this->addIfString($payload, 'name', $meta['name'] ?? null);
		$this->addIfString($payload, 'type_alias', $meta['type_alias'] ?? null);
		$this->addIfString($payload, 'content_id', $meta['content_id'] ?? null);
		$this->addIfString($payload, 'url', $meta['url'] ?? null);
		$this->addIfString($payload, 'filename', $meta['filename'] ?? null);
		$this->addIfString($payload, 'lang', $meta['lang'] ?? null);
		$this->addIfString($payload, 'created_at', $meta['created_at'] ?? null);
		$this->addIfString($payload, 'updated_at', $meta['updated_at'] ?? null);

		// Optional ints
		$this->addIfInt($payload, 'num_chunks', $meta['num_chunks'] ?? null);
		$this->addIfBoolInt($payload, 'archive', $meta['archive'] ?? null);
		$this->addIfBoolInt($payload, 'public', $meta['public'] ?? null);

		// Optional arrays (filterable)
		$tags = $this->normalizeStringArray($meta['tags'] ?? null, false, true);
		if (!empty($tags)) {
			$payload['tags'] = $tags;
		}

		$refUuids = $this->normalizeStringArray($meta['ref_uuids'] ?? null, true, false);
		if (!empty($refUuids)) {
			$payload['ref_uuids'] = $refUuids;
		}

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
			'updated_at',

			'num_chunks',
			'archive',
			'public',

			'tags',
			'ref_uuids',
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

	private function addIfInt(array &$payload, string $key, mixed $value): void {
		if ($value === null) {
			return;
		}

		if (is_int($value)) {
			if ($value > 0) {
				$payload[$key] = $value;
			}
			return;
		}

		if (is_string($value) && ctype_digit($value)) {
			$i = (int)$value;
			if ($i > 0) {
				$payload[$key] = $i;
			}
		}
	}

	private function addIfBoolInt(array &$payload, string $key, mixed $value): void {
		if ($value === null) {
			return;
		}

		if (is_bool($value)) {
			$payload[$key] = $value ? 1 : 0;
			return;
		}

		if (is_int($value)) {
			if ($value === 0 || $value === 1) {
				$payload[$key] = $value;
			}
			return;
		}

		if (is_string($value) && ctype_digit($value)) {
			$i = (int)$value;
			if ($i === 0 || $i === 1) {
				$payload[$key] = $i;
			}
		}
	}

	private function assertBoolIntMeta(array $meta, string $key): void {
		if (!array_key_exists($key, $meta)) {
			return;
		}

		$v = $meta[$key];

		if (is_bool($v)) {
			return;
		}

		if (is_int($v)) {
			if ($v === 0 || $v === 1) {
				return;
			}
			throw new \RuntimeException("metadata field '{$key}' must be 0 or 1 if provided.");
		}

		if (is_string($v) && ctype_digit($v)) {
			$i = (int)$v;
			if ($i === 0 || $i === 1) {
				return;
			}
		}

		throw new \RuntimeException("metadata field '{$key}' must be 0 or 1 if provided.");
	}

	/**
	 * @return array<int,string>
	 */
	private function normalizeStringArray(mixed $value, bool $upperHex, bool $lowercase): array {
		if ($value === null) {
			return [];
		}
		if (!is_array($value)) {
			throw new \RuntimeException('Expected array for payload field.');
		}

		$out = [];
		$seen = [];

		foreach ($value as $v) {
			$s = $this->asString($v);
			if ($s === null) {
				continue;
			}

			$s = trim($s);
			if ($s === '') {
				continue;
			}

			if ($upperHex) {
				$s = $this->asUpperHex($s);
				if ($s === '') {
					continue;
				}
			}

			if ($lowercase) {
				$s = strtolower($s);
			}

			if (isset($seen[$s])) {
				continue;
			}

			$seen[$s] = true;
			$out[] = $s;
		}

		return $out;
	}

	private function collectExtra(array $metadata, array $knownKeys): array {
		$extra = [];

		foreach ($metadata as $k => $v) {
			if (in_array($k, $knownKeys, true)) {
				continue;
			}

			if (in_array($k, ['job_id', 'attempts', 'locked_until', 'claim_token', 'claimed_at', 'state', 'error_message'], true)) {
				continue;
			}

			if (in_array($k, ['action', 'collectionKey', 'collection_key'], true)) {
				continue;
			}

			$extra[$k] = $v;
		}

		return $extra;
	}
}
