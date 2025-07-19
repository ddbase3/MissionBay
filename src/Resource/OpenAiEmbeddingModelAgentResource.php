<?php declare(strict_types=1);

namespace MissionBay\Resource;

use Base3\Api\IAiEmbeddingModel;
use MissionBay\Agent\AgentConfigValueResolver;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * OpenAiEmbeddingModelAgentResource
 *
 * Provides access to OpenAI's embedding models via a dockable resource.
 * Configuration is dynamic and supports resolver modes (e.g. fixed/apikey/model).
 */
class OpenAiEmbeddingModelAgentResource extends AbstractAgentResource implements IAiEmbeddingModel {

	protected IAgentConfigValueResolver $resolver;

	protected array|string|null $modelConfig = null;
	protected array|string|null $apikeyConfig = null;
	protected array|string|null $endpointConfig = null;

	protected array $resolvedOptions = [];

	public function __construct(IAgentConfigValueResolver $resolver, ?string $id = null) {
		parent::__construct($id);
		$this->resolver = $resolver;
	}

	public static function getName(): string {
		return 'openaiembeddingresource';
	}

	public function getDescription(): string {
		return 'Connects to OpenAI Embedding API and returns vector representations of texts.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);
		$this->modelConfig = $config['model'] ?? null;
		$this->apikeyConfig = $config['apikey'] ?? null;
		$this->endpointConfig = $config['endpoint'] ?? null;

		// resolve once and cache
		$this->resolvedOptions = [
			'model' => $this->resolver->resolveValue($this->modelConfig),
			'apikey' => $this->resolver->resolveValue($this->apikeyConfig),
			'endpoint' => $this->resolver->resolveValue($this->endpointConfig) ?? 'https://api.openai.com/v1/embeddings',
		];
	}

	public function getOptions(): array {
		return $this->resolvedOptions;
	}

	public function setOptions(array $options): void {
		// Optional override, e.g. from dynamic context
		$this->resolvedOptions = array_merge($this->resolvedOptions, $options);
	}

	public function embed(array $texts): array {
		if (empty($texts)) return [];

		$model = $this->resolvedOptions['model'] ?? 'text-embedding-3-small';
		$apikey = $this->resolvedOptions['apikey'] ?? null;
		$endpoint = $this->resolvedOptions['endpoint'] ?? 'https://api.openai.com/v1/embeddings';

		if (!$apikey) {
			throw new \RuntimeException("Missing API key for OpenAI embedding model.");
		}

		$payload = json_encode([
			'model' => $model,
			'input' => $texts
		]);

		$headers = [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $apikey
		];

		$ch = curl_init($endpoint);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

		$result = curl_exec($ch);
		if (curl_errno($ch)) {
			throw new \RuntimeException('OpenAI API request failed: ' . curl_error($ch));
		}

		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode !== 200) {
			throw new \RuntimeException("API request failed with status $httpCode: $result");
		}

		$data = json_decode($result, true);
		if (!isset($data['data']) || !is_array($data['data'])) {
			throw new \RuntimeException("Malformed OpenAI embedding response.");
		}

		return array_map(fn($item) => $item['embedding'] ?? [], $data['data']);
	}
}

