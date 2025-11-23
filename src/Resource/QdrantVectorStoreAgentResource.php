<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentVectorStore;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * QdrantVectorStoreAgentResource
 *
 * Unified Qdrant backend providing:
 * - vector upsert
 * - vector similarity search
 * - duplicate lookup by hash payload
 */
class QdrantVectorStoreAgentResource extends AbstractAgentResource implements IAgentVectorStore {

	protected IAgentConfigValueResolver $resolver;

	protected array|string|null $endpointConfig   = null;
	protected array|string|null $apikeyConfig     = null;
	protected array|string|null $collectionConfig = null;

	protected ?string $endpoint   = null;
	protected ?string $apikey     = null;
	protected ?string $collection = null;

	public function __construct(IAgentConfigValueResolver $resolver, ?string $id = null) {
		parent::__construct($id);
		$this->resolver = $resolver;
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

		$this->endpoint   = rtrim((string)$this->resolver->resolveValue($this->endpointConfig), '/');
		$this->apikey     = (string)$this->resolver->resolveValue($this->apikeyConfig);
		$this->collection = (string)$this->resolver->resolveValue($this->collectionConfig);
	}

	// ---------------------------------------------------------
	// Upsert (Store)
	// ---------------------------------------------------------

	/**
	 * Inserts or updates a Qdrant point.
	 *
	 * @param string $id
	 * @param array<float> $vector
	 * @param string $text
	 * @param array<string,mixed> $metadata
	 */
	public function upsert(string $id, array $vector, string $text, array $metadata = []): void {
		if (!$this->endpoint || !$this->collection) {
			throw new \RuntimeException("QdrantVectorStore: missing endpoint or collection.");
		}

		$url = "{$this->endpoint}/collections/{$this->collection}/points";

		// Flatten payload (no nested metadata)
		$payload = array_merge(
			[
				'text' => $text
			],
			$metadata
		);

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

	/**
	 * Checks whether a content hash already exists in the Qdrant payloads.
	 */
	public function existsByHash(string $hash): bool {
		if (!$this->endpoint || !$this->collection) {
			throw new \RuntimeException("QdrantVectorStore: missing endpoint or collection.");
		}

		$url = "{$this->endpoint}/collections/{$this->collection}/points/scroll";

		$body = [
			"filter" => [
				"must" => [
					[
						"key" => "hash",
						"match" => [
							"value" => $hash
						]
					]
				]
			],
			"limit" => 1,
			"with_payload" => true,
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

	/**
	 * Vector similarity search.
	 *
	 * @param array<float> $vector
	 * @param int $limit
	 * @param float|null $minScore
	 * @return array<int,array<string,mixed>>
	 */
	public function search(array $vector, int $limit = 3, ?float $minScore = null): array {
		if (!$this->endpoint || !$this->collection) {
			throw new \RuntimeException("QdrantVectorSearch: endpoint or collection not configured.");
		}

		$url = "{$this->endpoint}/collections/{$this->collection}/points/search";

		$body = [
			"vector"       => $vector,
			"limit"        => $limit,
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
			throw new \RuntimeException("QdrantVectorSearch: request failed: " . curl_error($ch));
		}

		curl_close($ch);

		$data = json_decode($response, true);
		if (!isset($data['result']) || !is_array($data['result'])) {
			return [];
		}

		$results = [];
		foreach ($data['result'] as $hit) {
			$score = $hit['score'] ?? null;
			if ($minScore !== null && $score < $minScore) {
				continue;
			}

			$results[] = [
				'id'      => $hit['id'] ?? null,
				'score'   => $score,
				'payload' => $hit['payload'] ?? []
			];
		}

		return $results;
	}
}
