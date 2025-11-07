<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Resource\AbstractAgentResource;
use AssistantFoundation\Api\IVectorSearch;

/**
 * QdrantVectorSearch
 *
 * Provides vector similarity search using Qdrant.
 * Configurable with endpoint, API key, and collection.
 */
class QdrantVectorSearch extends AbstractAgentResource implements IVectorSearch {

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
		return 'qdrantvectorsearch';
	}

	public function getDescription(): string {
		return 'Performs similarity search in a Qdrant collection.';
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

	/**
	 * @param array<float> $vector
	 * @param int $limit
	 * @param float|null $minScore
	 * @return array<int, array<string,mixed>>
	 */
	public function search(array $vector, int $limit = 3, ?float $minScore = null): array {
		if (!$this->endpoint || !$this->collection) {
			throw new \RuntimeException("QdrantVectorSearch: endpoint or collection not configured.");
		}

		$url = "{$this->endpoint}/collections/{$this->collection}/points/search";
		$body = [
			"vector" => $vector,
			"limit" => $limit,
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
			if ($minScore !== null && $score < $minScore) continue;

			$results[] = [
				'id'      => $hit['id'] ?? null,
				'score'   => $score,
				'payload' => $hit['payload'] ?? []
			];
		}

		return $results;
	}
}

