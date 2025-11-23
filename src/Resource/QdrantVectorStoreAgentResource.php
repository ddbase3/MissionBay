<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentVectorStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentRagPayloadNormalizer;

/**
 * QdrantVectorStoreAgentResource
 *
 * Unified Qdrant backend providing:
 * - vector upsert
 * - vector similarity search
 * - duplicate lookup by hash payload
 * - collection lifecycle management
 */
class QdrantVectorStoreAgentResource extends AbstractAgentResource implements IAgentVectorStore {

	protected IAgentConfigValueResolver $resolver;
	protected IAgentRagPayloadNormalizer $normalizer;

	protected array|string|null $endpointConfig   = null;
	protected array|string|null $apikeyConfig     = null;
	protected array|string|null $collectionConfig = null;
	protected array|string|null $vectorSizeConfig = null;
	protected array|string|null $distanceConfig   = null;

	protected ?string $endpoint   = null;
	protected ?string $apikey     = null;
	protected ?string $collection = null;

	protected int $vectorSize = 1536;
	protected string $distance = 'Cosine';

	public function __construct(
		IAgentConfigValueResolver $resolver,
		IAgentRagPayloadNormalizer $normalizer,
		?string $id = null
	) {
		parent::__construct($id);
		$this->resolver   = $resolver;
		$this->normalizer = $normalizer;
	}

	public static function getName(): string {
		return strtolower(__CLASS__);
	}

	public function getDescription(): string {
		return 'Provides vector upsert, search, and duplicate detection for Qdrant.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->endpointConfig   = $config['endpoint']   ?? null;
		$this->apikeyConfig     = $config['apikey']     ?? null;
		$this->collectionConfig = $config['collection'] ?? null;
		$this->vectorSizeConfig = $config['vector_size'] ?? null;
		$this->distanceConfig   = $config['distance'] ?? null;

		$this->endpoint   = rtrim((string)$this->resolver->resolveValue($this->endpointConfig), '/');
		$this->apikey     = (string)$this->resolver->resolveValue($this->apikeyConfig);
		$this->collection = (string)$this->resolver->resolveValue($this->collectionConfig);

		$size = $this->resolver->resolveValue($this->vectorSizeConfig);
		if (is_numeric($size)) {
			$this->vectorSize = (int)$size;
		}

		$dist = $this->resolver->resolveValue($this->distanceConfig);
		if (is_string($dist) && $dist !== '') {
			$this->distance = $dist;
		}
	}

	// ---------------------------------------------------------
	// Upsert (Store)
	// ---------------------------------------------------------

	/**
	 * @param string $id
	 * @param array<float> $vector
	 * @param string $text
	 * @param string $hash
	 * @param array<string,mixed> $metadata
	 */
	public function upsert(string $id, array $vector, string $text, string $hash, array $metadata = []): void {
		if (!$this->endpoint || !$this->collection) {
			throw new \RuntimeException("QdrantVectorStore: missing endpoint or collection.");
		}

		$url = "{$this->endpoint}/collections/{$this->collection}/points";

		// Use normalizer to build flat payload
		$payload = $this->normalizer->normalize($text, $hash, $metadata);

		$body = [
			"points" => [
				[
					"id"      => $id,
					"vector"  => $vector,
					"payload" => $payload
				]
			]
		];

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Content-Type: application/json",
			"api-key: {$this->apikey}"
		]);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

		$response = curl_exec($ch);
		if ($response === false) {
			throw new \RuntimeException("QdrantVectorStore: upsert failed: " . curl_error($ch));
		}

		curl_close($ch);
	}

	// ---------------------------------------------------------
	// Duplicate Detection
	// ---------------------------------------------------------

	public function existsByHash(string $hash): bool {
		if (!$this->endpoint || !$this->collection) {
			throw new \RuntimeException("QdrantVectorStore: missing endpoint or collection.");
		}

		$url = "{$this->endpoint}/collections/{$this->collection}/points/scroll";

		$body = [
			"filter" => [
				"must" => [
					[
						"key"   => "hash",
						"match" => ["value" => $hash]
					]
				]
			],
			"limit"        => 1,
			"with_payload" => true,
			"with_vector"  => false
		];

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Content-Type: application/json",
			"api-key: {$this->apikey}"
		]);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

		$response = curl_exec($ch);
		if ($response === false) {
			throw new \RuntimeException("QdrantVectorStore: existsByHash query failed: " . curl_error($ch));
		}

		curl_close($ch);

		$data = json_decode($response, true);

		if (!isset($data['result']['points'])) {
			return false;
		}

		return count($data['result']['points']) > 0;
	}

	// ---------------------------------------------------------
	// Search
	// ---------------------------------------------------------

	public function search(array $vector, int $limit = 3, ?float $minScore = null): array {
		if (!$this->endpoint || !$this->collection) {
			throw new \RuntimeException("QdrantVectorStore: endpoint or collection not configured.");
		}

		$url = "{$this->endpoint}/collections/{$this->collection}/points/search";

		$body = [
			"vector"      => $vector,
			"limit"       => $limit,
			"with_payload"=> true,
			"with_vector" => false
		];

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Content-Type: application/json",
			"api-key: {$this->apikey}"
		]);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

		$response = curl_exec($ch);
		if ($response === false) {
			throw new \RuntimeException("QdrantVectorStore: request failed: " . curl_error($ch));
		}
		curl_close($ch);

		$data = json_decode($response, true);
		if (!isset($data['result']) || !is_array($data['result'])) {
			return [];
		}

		$results = [];
		foreach ($data['result'] as $hit) {
			$score = $hit['score'] ?? null;
			if ($minScore !== null && $score < $minScore) continue;

			$results[] = [
				'id'      => $hit['id'] ?? null,
				'score'   => $score,
				'payload' => $hit['payload'] ?? []
			];
		}

		return $results;
	}

	// ---------------------------------------------------------
	// Collection lifecycle
	// ---------------------------------------------------------

	public function createCollection(): void {
		if (!$this->endpoint || !$this->collection) {
			throw new \RuntimeException("QdrantVectorStore: missing endpoint or collection.");
		}

		$url = "{$this->endpoint}/collections/{$this->collection}";

		$schema = $this->normalizer->getSchema();

		$body = [
			"vectors" => [
				"size"     => $this->vectorSize,
				"distance" => $this->distance
			]
		];

		// Optional: attach logical payload schema (Qdrant will ignore unknown keys)
		if (!empty($schema)) {
			$body["payload_schema"] = $schema['fields'] ?? $schema;
		}

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Content-Type: application/json",
			"api-key: {$this->apikey}"
		]);
		curl_setopt($ch, CURLOPT_PUT, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

		$response = curl_exec($ch);
		if ($response === false) {
			throw new \RuntimeException("QdrantVectorStore: createCollection failed: " . curl_error($ch));
		}

		curl_close($ch);
	}

	public function deleteCollection(): void {
		if (!$this->endpoint || !$this->collection) {
			throw new \RuntimeException("QdrantVectorStore: missing endpoint or collection.");
		}

		$url = "{$this->endpoint}/collections/{$this->collection}";

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Content-Type: application/json",
			"api-key: {$this->apikey}"
		]);

		$response = curl_exec($ch);
		if ($response === false) {
			throw new \RuntimeException("QdrantVectorStore: deleteCollection failed: " . curl_error($ch));
		}

		curl_close($ch);
	}
}
