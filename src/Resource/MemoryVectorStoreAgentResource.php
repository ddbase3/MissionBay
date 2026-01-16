<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentVectorStore;
use MissionBay\Dto\AgentEmbeddingChunk;

/**
 * MemoryVectorStoreAgentResource
 *
 * Simple in-memory vector store for development and testing.
 * Not persistent and not optimized for similarity search.
 *
 * Notes:
 * - Multi-collection is supported by using collectionKey buckets.
 * - No normalizer here: payload is stored "as provided" via AgentEmbeddingChunk->metadata,
 *   plus mandatory fields (text, hash, chunk_index).
 */
final class MemoryVectorStoreAgentResource extends AbstractAgentResource implements IAgentVectorStore {

	/** @var array<string,array<string,mixed>> */
	protected array $store = [];

	public static function getName(): string {
		return 'memoryvectorstoreagentresource';
	}

	public function getDescription(): string {
		return 'Non-persistent in-memory vector store for development and testing.';
	}

	// ---------------------------------------------------------
	// Internal helpers
	// ---------------------------------------------------------

	/**
	 * @return array<string,mixed>
	 */
	protected function &getBucket(string $collectionKey): array {
		$collectionKey = trim($collectionKey);
		if ($collectionKey === '') {
			$collectionKey = '_default';
		}

		if (!isset($this->store[$collectionKey]) || !is_array($this->store[$collectionKey])) {
			$this->store[$collectionKey] = [
				'points' => [] // id => ['vector' => float[], 'payload' => array]
			];
		}

		return $this->store[$collectionKey];
	}

	// ---------------------------------------------------------
	// UPSERT
	// ---------------------------------------------------------

	public function upsert(AgentEmbeddingChunk $chunk): void {
		$bucket = &$this->getBucket($chunk->collectionKey);

		$uuid = $this->generateUuid();

		$payload = array_merge($chunk->metadata ?? [], [
			'text' => $chunk->text,
			'hash' => $chunk->hash,
			'chunk_index' => $chunk->chunkIndex
		]);

		$bucket['points'][$uuid] = [
			'vector' => $chunk->vector,
			'payload' => $payload
		];
	}

	// ---------------------------------------------------------
	// EXISTS
	// ---------------------------------------------------------

	public function existsByHash(string $collectionKey, string $hash): bool {
		$hash = trim($hash);
		if ($hash === '') {
			return false;
		}

		return $this->existsByFilter($collectionKey, ['hash' => $hash]);
	}

	public function existsByFilter(string $collectionKey, array $filter): bool {
		$bucket = $this->getBucket($collectionKey);
		$points = $bucket['points'] ?? [];

		foreach ($points as $item) {
			$payload = $item['payload'] ?? [];
			if ($this->matchesFlatFilter($payload, $filter)) {
				return true;
			}
		}

		return false;
	}

	// ---------------------------------------------------------
	// DELETE
	// ---------------------------------------------------------

	public function deleteByFilter(string $collectionKey, array $filter): int {
		$bucket = &$this->getBucket($collectionKey);
		$points = $bucket['points'] ?? [];

		$deleted = 0;

		foreach ($points as $id => $item) {
			$payload = $item['payload'] ?? [];
			if ($this->matchesFlatFilter($payload, $filter)) {
				unset($bucket['points'][$id]);
				$deleted++;
			}
		}

		return $deleted;
	}

	// ---------------------------------------------------------
	// SEARCH
	// ---------------------------------------------------------

	public function search(string $collectionKey, array $vector, int $limit = 3, ?float $minScore = null, ?array $filterSpec = null): array {
		$bucket = $this->getBucket($collectionKey);
		$points = $bucket['points'] ?? [];

		$results = [];

		foreach ($points as $id => $item) {
			$payload = $item['payload'] ?? [];

			if ($filterSpec !== null && !$this->matchesFilterSpec($payload, $filterSpec)) {
				continue;
			}

			$score = $this->cosineSimilarity($vector, $item['vector'] ?? []);
			if ($minScore !== null && $score < $minScore) {
				continue;
			}

			$results[] = [
				'id' => $id,
				'score' => $score,
				'payload' => $payload
			];
		}

		usort($results, static function(array $a, array $b): int {
			return ($b['score'] ?? 0.0) <=> ($a['score'] ?? 0.0);
		});

		if ($limit < 1) {
			return [];
		}

		return array_slice($results, 0, $limit);
	}

	protected function cosineSimilarity(array $a, array $b): float {
		if (empty($a) || empty($b) || count($a) !== count($b)) {
			return 0.0;
		}

		$dot = 0.0;
		$normA = 0.0;
		$normB = 0.0;

		$len = count($a);
		for ($i = 0; $i < $len; $i++) {
			$av = (float)$a[$i];
			$bv = (float)$b[$i];
			$dot += $av * $bv;
			$normA += $av * $av;
			$normB += $bv * $bv;
		}

		if ($normA == 0.0 || $normB == 0.0) {
			return 0.0;
		}

		return $dot / (sqrt($normA) * sqrt($normB));
	}

	// ---------------------------------------------------------
	// COLLECTION LIFECYCLE
	// ---------------------------------------------------------

	public function createCollection(string $collectionKey): void {
		$bucket = &$this->getBucket($collectionKey);
		$bucket['points'] = [];
	}

	public function deleteCollection(string $collectionKey): void {
		$collectionKey = trim($collectionKey);
		if ($collectionKey === '') {
			$collectionKey = '_default';
		}

		if (isset($this->store[$collectionKey])) {
			unset($this->store[$collectionKey]);
		}
	}

	public function getInfo(string $collectionKey): array {
		$bucket = $this->getBucket($collectionKey);
		$points = $bucket['points'] ?? [];

		return [
			'type' => 'memory',
			'collection_key' => $collectionKey,
			'collection' => 'in-memory:' . (trim($collectionKey) !== '' ? $collectionKey : '_default'),
			'count' => is_array($points) ? count($points) : 0,
			'details' => [
				'persistent' => false,
				'description' => 'Simple volatile memory-based vector store.'
			]
		];
	}

	// ---------------------------------------------------------
	// FILTER MATCHER (flat + FilterSpec)
	// ---------------------------------------------------------

	/**
	 * Flat filter:
	 * - ['key' => 'value']       => must match
	 * - ['key' => ['a','b']]     => must match any value (OR on same key)
	 *
	 * @param array<string,mixed> $payload
	 * @param array<string,mixed> $filter
	 */
	protected function matchesFlatFilter(array $payload, array $filter): bool {
		foreach ($filter as $key => $value) {
			$actual = $payload[$key] ?? null;

			if (is_array($value)) {
				$ok = false;
				foreach ($value as $v) {
					if ($actual === $v) {
						$ok = true;
						break;
					}
				}
				if (!$ok) {
					return false;
				}
				continue;
			}

			if ($actual !== $value) {
				return false;
			}
		}

		return true;
	}

	/**
	 * FilterSpec (internal format):
	 * - must:     key => scalar|array (AND; array = OR on same key)
	 * - any:      key => scalar|array (OR-group; at least one condition must match)
	 * - must_not: key => scalar|array (AND; none must match)
	 *
	 * For payload arrays:
	 * - if payload value is an array, scalar match checks "contains".
	 *
	 * @param array<string,mixed> $payload
	 * @param array<string,mixed> $spec
	 */
	protected function matchesFilterSpec(array $payload, array $spec): bool {
		$must = $spec['must'] ?? null;
		if (is_array($must) && !$this->matchesSpecGroupAll($payload, $must)) {
			return false;
		}

		$mustNot = $spec['must_not'] ?? null;
		if (is_array($mustNot) && $this->matchesSpecGroupAny($payload, $mustNot)) {
			return false;
		}

		$any = $spec['any'] ?? null;
		if (is_array($any) && !empty($any)) {
			if (!$this->matchesSpecGroupAny($payload, $any)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<string,mixed> $payload
	 * @param array<string,mixed> $group
	 */
	private function matchesSpecGroupAll(array $payload, array $group): bool {
		foreach ($group as $key => $expected) {
			if (!$this->matchesPayloadField($payload, (string)$key, $expected)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param array<string,mixed> $payload
	 * @param array<string,mixed> $group
	 */
	private function matchesSpecGroupAny(array $payload, array $group): bool {
		foreach ($group as $key => $expected) {
			if ($this->matchesPayloadField($payload, (string)$key, $expected)) {
				return true;
			}
		}
		return false;
	}

	private function matchesPayloadField(array $payload, string $key, mixed $expected): bool {
		$key = trim($key);
		if ($key === '') {
			return false;
		}

		$actual = $payload[$key] ?? null;

		if (is_array($expected)) {
			foreach ($expected as $v) {
				if ($this->matchesPayloadValue($actual, $v)) {
					return true;
				}
			}
			return false;
		}

		return $this->matchesPayloadValue($actual, $expected);
	}

	private function matchesPayloadValue(mixed $actual, mixed $expected): bool {
		if (is_array($actual)) {
			foreach ($actual as $item) {
				if ($item === $expected) {
					return true;
				}
			}
			return false;
		}

		return $actual === $expected;
	}

	/**
	 * Generates UUID v4.
	 */
	protected function generateUuid(): string {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
	}
}
