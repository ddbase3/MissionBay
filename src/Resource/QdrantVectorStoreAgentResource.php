<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentVectorStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentRagPayloadNormalizer;
use MissionBay\Dto\AgentEmbeddingChunk;

/**
 * QdrantVectorStoreAgentResource
 *
 * Qdrant-backed VectorStore (multi-collection).
 *
 * Key rules:
 * - Routing is driven ONLY by collectionKey provided upstream.
 * - Physical collection name + schema + vector size + distance come from the Normalizer.
 * - This store builds/validates payload via normalizer, then writes to Qdrant.
 *
 * Config:
 * - endpoint (required)
 * - apikey (required)
 * - create_payload_indexes (optional, default false)
 *
 * Note:
 * - No "collection" config here anymore.
 *   Collections are owned by the normalizer and addressed via collectionKey.
 */
final class QdrantVectorStoreAgentResource extends AbstractAgentResource implements IAgentVectorStore {

	protected IAgentConfigValueResolver $resolver;
	protected IAgentRagPayloadNormalizer $normalizer;

	protected array|string|null $endpointConfig = null;
	protected array|string|null $apikeyConfig = null;
	protected mixed $createPayloadIndexesConfig = null;

	protected ?string $endpoint = null;
	protected ?string $apikey = null;

	// Safety: default OFF so first test cannot fail due to schema mismatch.
	protected bool $createPayloadIndexes = false;

	public function __construct(
		IAgentConfigValueResolver $resolver,
		IAgentRagPayloadNormalizer $normalizer,
		?string $id = null
	) {
		parent::__construct($id);
		$this->resolver = $resolver;
		$this->normalizer = $normalizer;
	}

	public static function getName(): string {
		return 'qdrantvectorstoreagentresource';
	}

	public function getDescription(): string {
		return 'Provides vector upsert, search, and duplicate detection for Qdrant (multi-collection).';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->endpointConfig = $config['endpoint'] ?? null;
		$this->apikeyConfig = $config['apikey'] ?? null;
		$this->createPayloadIndexesConfig = $config['create_payload_indexes'] ?? null;

		$endpoint = (string)$this->resolver->resolveValue($this->endpointConfig);
		$apikey = (string)$this->resolver->resolveValue($this->apikeyConfig);

		$endpoint = rtrim(trim($endpoint), '/');

		if ($endpoint === '') {
			throw new \InvalidArgumentException('QdrantVectorStore: endpoint is required.');
		}
		if ($apikey === '') {
			throw new \InvalidArgumentException('QdrantVectorStore: apikey is required.');
		}

		$this->endpoint = $endpoint;
		$this->apikey = $apikey;

		$flag = $this->resolver->resolveValue($this->createPayloadIndexesConfig);
		if (is_bool($flag)) {
			$this->createPayloadIndexes = $flag;
		} else if (is_string($flag)) {
			$this->createPayloadIndexes = in_array(strtolower(trim($flag)), ['1', 'true', 'yes', 'on'], true);
		} else if (is_int($flag)) {
			$this->createPayloadIndexes = $flag === 1;
		}
	}

	// ---------------------------------------------------------
	// UPSERT
	// ---------------------------------------------------------

	public function upsert(AgentEmbeddingChunk $chunk): void {
		$this->assertReady();

		// Strict: normalizer validates and builds payload (no guessing here)
		$this->normalizer->validate($chunk);
		$collection = $this->normalizer->getBackendCollectionName($chunk->collectionKey);

		$url = "{$this->endpoint}/collections/{$collection}/points?wait=true";

		$uuid = $this->generateUuid();
		$payload = $this->normalizer->buildPayload($chunk);

		$body = [
			"points" => [
				[
					"id" => $uuid,
					"vector" => $chunk->vector,
					"payload" => $payload
				]
			]
		];

		$r = $this->curlJson('PUT', $url, $body);

		$http = (int)($r['http'] ?? 0);
		if ($http < 200 || $http >= 300) {
			throw new \RuntimeException("Qdrant upsert failed HTTP $http: " . ($r['error'] ?? '') . ' ' . ($r['raw'] ?? ''));
		}
	}

	// ---------------------------------------------------------
	// EXISTS BY HASH
	// ---------------------------------------------------------

	public function existsByHash(string $collectionKey, string $hash): bool {
		$hash = trim($hash);
		if ($hash === '') {
			return false;
		}
		return $this->existsByFilter($collectionKey, ['hash' => $hash]);
	}

	// ---------------------------------------------------------
	// EXISTS BY FILTER
	// ---------------------------------------------------------

	public function existsByFilter(string $collectionKey, array $filter): bool {
		$this->assertReady();

		$collection = $this->normalizer->getBackendCollectionName($collectionKey);
		$url = "{$this->endpoint}/collections/{$collection}/points/scroll";

		$body = [
			"filter" => $this->buildQdrantFilter($filter),
			"limit" => 1,
			"with_payload" => false,
			"with_vector" => false
		];

		$r = $this->curlJson('POST', $url, $body);

		$data = json_decode((string)($r['raw'] ?? ''), true);
		return isset($data['result']['points']) && is_array($data['result']['points']) && count($data['result']['points']) > 0;
	}

	// ---------------------------------------------------------
	// DELETE BY FILTER
	// ---------------------------------------------------------

	public function deleteByFilter(string $collectionKey, array $filter): int {
		$this->assertReady();

		$collection = $this->normalizer->getBackendCollectionName($collectionKey);
		$url = "{$this->endpoint}/collections/{$collection}/points/delete?wait=true";

		$body = [
			"filter" => $this->buildQdrantFilter($filter)
		];

		$r = $this->curlJson('POST', $url, $body);

		$http = (int)($r['http'] ?? 0);
		if ($http < 200 || $http >= 300) {
			throw new \RuntimeException("Qdrant deleteByFilter failed HTTP $http: " . ($r['error'] ?? '') . ' ' . ($r['raw'] ?? ''));
		}

		$data = json_decode((string)($r['raw'] ?? ''), true);

		$deleted = $data['result']['deleted'] ?? null;
		if (is_int($deleted)) {
			return $deleted;
		}

		$points = $data['result']['points'] ?? null;
		if (is_array($points)) {
			return count($points);
		}

		return 0;
	}

	// ---------------------------------------------------------
	// SEARCH
	// ---------------------------------------------------------

	public function search(string $collectionKey, array $vector, int $limit = 3, ?float $minScore = null): array {
		$this->assertReady();

		$collection = $this->normalizer->getBackendCollectionName($collectionKey);
		$url = "{$this->endpoint}/collections/{$collection}/points/search";

		$body = [
			"vector" => $vector,
			"limit" => $limit,
			"with_payload" => true,
			"with_vector" => false
		];

		$r = $this->curlJson('POST', $url, $body);

		$data = json_decode((string)($r['raw'] ?? ''), true);
		if (!isset($data['result']) || !is_array($data['result'])) {
			return [];
		}

		$out = [];
		foreach ($data['result'] as $hit) {
			$score = $hit['score'] ?? null;
			if (!is_numeric($score)) {
				continue;
			}
			$score = (float)$score;
			if ($minScore !== null && $score < $minScore) {
				continue;
			}

			$out[] = [
				'id' => $hit['id'] ?? null,
				'score' => $score,
				'payload' => $hit['payload'] ?? []
			];
		}

		return $out;
	}

	// ---------------------------------------------------------
	// CREATE COLLECTION
	// ---------------------------------------------------------

	public function createCollection(string $collectionKey): void {
		$this->assertReady();

		$collection = $this->normalizer->getBackendCollectionName($collectionKey);

		$vectorSize = $this->normalizer->getVectorSize($collectionKey);
		$distance = $this->normalizer->getDistance($collectionKey);

		$url = "{$this->endpoint}/collections/{$collection}";

		$body = [
			"vectors" => [
				"size" => $vectorSize,
				"distance" => $distance
			]
		];

		$r = $this->curlJson('PUT', $url, $body);

		$http = (int)($r['http'] ?? 0);
		if ($http < 200 || $http >= 300) {
			throw new \RuntimeException("Qdrant createCollection HTTP $http: " . ($r['error'] ?? '') . ' ' . ($r['raw'] ?? ''));
		}

		if ($this->createPayloadIndexes) {
			$this->createPayloadIndexes($collectionKey);
		}
	}

	// ---------------------------------------------------------
	// DELETE COLLECTION
	// ---------------------------------------------------------

	public function deleteCollection(string $collectionKey): void {
		$this->assertReady();

		$collection = $this->normalizer->getBackendCollectionName($collectionKey);
		$url = "{$this->endpoint}/collections/{$collection}";

		$r = $this->curlJson('DELETE', $url, null);

		$http = (int)($r['http'] ?? 0);
		if ($http < 200 || $http >= 300) {
			throw new \RuntimeException("Qdrant deleteCollection HTTP $http: " . ($r['raw'] ?? ''));
		}
	}

	// ---------------------------------------------------------
	// GET COLLECTION INFO
	// ---------------------------------------------------------

	public function getInfo(string $collectionKey): array {
		$this->assertReady();

		$collection = $this->normalizer->getBackendCollectionName($collectionKey);
		$url = "{$this->endpoint}/collections/{$collection}";

		$r = $this->curlJson('GET', $url, null);
		$data = json_decode((string)($r['raw'] ?? ''), true);

		return [
			'collection_key' => $collectionKey,
			'collection' => $collection,
			'vector_size' => $this->normalizer->getVectorSize($collectionKey),
			'distance' => $this->normalizer->getDistance($collectionKey),
			'payload_schema' => $data['result']['payload_schema'] ?? [],
			'qdrant_raw' => $data
		];
	}

	// ---------------------------------------------------------
	// PAYLOAD INDEX CREATION (optional)
	// ---------------------------------------------------------

	protected function createPayloadIndexes(string $collectionKey): void {
		$schema = $this->normalizer->getSchema($collectionKey);
		if (empty($schema) || !is_array($schema)) {
			return;
		}

		$collection = $this->normalizer->getBackendCollectionName($collectionKey);

		foreach ($schema as $field => $def) {
			$url = "{$this->endpoint}/collections/{$collection}/index";

			$body = [
				"field_name" => (string)$field,
				"field_schema" => $def
			];

			$r = $this->curlJson('PUT', $url, $body);

			$http = (int)($r['http'] ?? 0);
			if ($http < 200 || $http >= 300) {
				// intentionally non-fatal (index creation can vary by Qdrant version)
				return;
			}
		}
	}

	// ---------------------------------------------------------
	// FILTER BUILDER
	// ---------------------------------------------------------

	/**
	 * Builds a simple Qdrant filter from a flat associative array.
	 *
	 * Supported:
	 * - ['key' => 'value']            => must match
	 * - ['key' => ['a','b','c']]      => should match any (OR)
	 *
	 * @param array<string,mixed> $filter
	 * @return array<string,mixed>
	 */
	protected function buildQdrantFilter(array $filter): array {
		$must = [];
		$should = [];

		foreach ($filter as $key => $value) {
			if (is_array($value)) {
				foreach ($value as $v) {
					$should[] = [
						"key" => (string)$key,
						"match" => ["value" => $v]
					];
				}
				continue;
			}

			$must[] = [
				"key" => (string)$key,
				"match" => ["value" => $value]
			];
		}

		$out = [];
		if ($must) {
			$out['must'] = $must;
		}
		if ($should) {
			$out['should'] = $should;
		}
		if (!$out) {
			$out['must'] = [];
		}

		return $out;
	}

	// ---------------------------------------------------------
	// CURL HELPER
	// ---------------------------------------------------------

	/**
	 * @param string $method GET|POST|PUT|DELETE
	 * @param string $url
	 * @param array<string,mixed>|null $body
	 * @return array<string,mixed>
	 */
	protected function curlJson(string $method, string $url, ?array $body): array {
		$ch = curl_init($url);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Content-Type: application/json",
			"api-key: {$this->apikey}"
		]);

		if ($method === 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
		} else if ($method !== 'GET') {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		}

		if ($body !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
		}

		$raw = curl_exec($ch);
		$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);

		curl_close($ch);

		return [
			'raw' => $raw,
			'http' => $http,
			'error' => $error
		];
	}

	// ---------------------------------------------------------
	// UUID GENERATOR
	// ---------------------------------------------------------

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

	private function assertReady(): void {
		if (!$this->endpoint || trim($this->endpoint) === '') {
			throw new \RuntimeException('QdrantVectorStore not configured: endpoint missing.');
		}
		if (!$this->apikey || trim($this->apikey) === '') {
			throw new \RuntimeException('QdrantVectorStore not configured: apikey missing.');
		}
	}
}
