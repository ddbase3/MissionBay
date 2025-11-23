<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentVectorStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentRagPayloadNormalizer;

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
		return 'qdrantvectorstoreagentresource';
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
		if (is_numeric($size)) $this->vectorSize = (int)$size;

		$dist = $this->resolver->resolveValue($this->distanceConfig);
		if (is_string($dist) && $dist !== '') $this->distance = $dist;
	}

	// ---------------------------------------------------------
	// UPSERT - Always UUID
	// ---------------------------------------------------------

	public function upsert(string $id, array $vector, string $text, string $hash, array $metadata = []): void {
		$url = "{$this->endpoint}/collections/{$this->collection}/points?wait=true";

		$uuid = $this->generateUuid();
		$payload = $this->normalizer->normalize($text, $hash, $metadata);

		$body = [
			"points" => [
				[
					"id"      => $uuid,
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
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

		$r = curl_exec($ch);
		$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err = curl_error($ch);

		curl_close($ch);

		if ($r === false || $http < 200 || $http >= 300) {
			throw new \RuntimeException("upsert failed HTTP $http: $err $r");
		}
	}

	// ---------------------------------------------------------
	// EXISTS BY HASH
	// ---------------------------------------------------------

	public function existsByHash(string $hash): bool {
		$url = "{$this->endpoint}/collections/{$this->collection}/points/scroll";

		$body = [
			"filter" => [
				"must" => [
					["key" => "hash", "match" => ["value" => $hash]]
				]
			],
			"limit" => 1,
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

		$r = curl_exec($ch);
		curl_close($ch);

		$data = json_decode($r, true);
		return isset($data['result']['points']) && count($data['result']['points']) > 0;
	}

	// ---------------------------------------------------------
	// SEARCH
	// ---------------------------------------------------------

	public function search(array $vector, int $limit = 3, ?float $minScore = null): array {
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

		$r = curl_exec($ch);
		curl_close($ch);

		$data = json_decode($r, true);
		if (!isset($data['result'])) return [];

		$out = [];
		foreach ($data['result'] as $hit) {
			$score = $hit['score'] ?? null;
			if ($minScore !== null && $score < $minScore) continue;

			$out[] = [
				'id'      => $hit['id'] ?? null,
				'score'   => $score,
				'payload' => $hit['payload'] ?? []
			];
		}
		return $out;
	}

	// ---------------------------------------------------------
	// CREATE COLLECTION
	// ---------------------------------------------------------

	public function createCollection(): void {
		$url = "{$this->endpoint}/collections/{$this->collection}";

		$body = [
			"vectors" => [
				"size"     => $this->vectorSize,
				"distance" => $this->distance
			]
		];

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Content-Type: application/json",
			"api-key: {$this->apikey}"
		]);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

		$r = curl_exec($ch);
		$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err = curl_error($ch);

		curl_close($ch);

		if ($r === false || $http < 200 || $http >= 300) {
			throw new \RuntimeException("createCollection HTTP $http: $err $r");
		}

		$this->createPayloadIndexes();
	}

	// ---------------------------------------------------------
	// DELETE COLLECTION
	// ---------------------------------------------------------

	public function deleteCollection(): void {
		$url = "{$this->endpoint}/collections/{$this->collection}";

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Content-Type: application/json",
			"api-key: {$this->apikey}"
		]);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');

		$r = curl_exec($ch);
		$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($r === false || $http < 200 || $http >= 300) {
			throw new \RuntimeException("deleteCollection HTTP $http: $r");
		}
	}

	// ---------------------------------------------------------
	// PAYLOAD INDEX CREATION
	// ---------------------------------------------------------

	protected function createPayloadIndexes(): void {
		$schema = $this->normalizer->getSchema();
		if (empty($schema)) return;

		foreach ($schema as $field => $def) {
			$url = "{$this->endpoint}/collections/{$this->collection}/index";

			$body = [
				"field_name"   => $field,
				"field_schema" => $def
			];

			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
				"Content-Type: application/json",
				"api-key: {$this->apikey}"
			]);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

			$r = curl_exec($ch);
			$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if ($http < 200 || $http >= 300) {
				throw new \RuntimeException("createPayloadIndex($field) HTTP $http: $r");
			}
		}
	}

	// ---------------------------------------------------------
	// GET COLLECTION INFO
	// ---------------------------------------------------------

	public function getInfo(): array {
		$url = "{$this->endpoint}/collections/{$this->collection}";

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Content-Type: application/json",
			"api-key: {$this->apikey}"
		]);
		$r = curl_exec($ch);
		curl_close($ch);

		$data = json_decode($r, true);

		return [
			'collection'     => $this->collection,
			'vector_size'    => $this->vectorSize,
			'distance'       => $this->distance,
			'payload_schema' => $data['result']['payload_schema'] ?? [],
			'qdrant_raw'     => $data
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
}
