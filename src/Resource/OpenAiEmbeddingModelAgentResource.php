<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace MissionBay\Resource;

use AssistantFoundation\Api\IAiEmbeddingModel;
use AssistantFoundation\Api\IAiProvider;
use AssistantFoundation\Dto\AiEmbeddingResult;
use Base3\Api\IClassMap;
use MissionBay\Ai\AiResultNormalizer;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Transport\OpenAiTransport;

/**
 * OpenAiEmbeddingModelAgentResource
 *
 * Direct OpenAI embedding resource.
 * This resource is intentionally independent from service-embedding
 * configuration and is kept for direct/static agent flow setups.
 */
class OpenAiEmbeddingModelAgentResource extends AbstractAgentResource implements IAiEmbeddingModel {

	protected IAgentConfigValueResolver $resolver;
	protected IClassMap $classMap;

	protected array|string|null $modelConfig = null;
	protected array|string|null $apikeyConfig = null;
	protected array|string|null $endpointConfig = null;

	protected array $resolvedOptions = [];

	protected ?OpenAiTransport $provider = null;

	public function __construct(IAgentConfigValueResolver $resolver, IClassMap $classMap, ?string $id = null) {
		parent::__construct($id);
		$this->resolver = $resolver;
		$this->classMap = $classMap;
	}

	public static function getName(): string {
		return 'openaiembeddingmodelagentresource';
	}

	public function getDescription(): string {
		return 'Connects to OpenAI Embedding API and returns vector representations of texts.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->modelConfig = $config['model'] ?? null;
		$this->apikeyConfig = $config['apikey'] ?? null;
		$this->endpointConfig = $config['endpoint'] ?? null;

		$this->resolvedOptions = [
			'model' => $this->resolver->resolveValue($this->modelConfig) ?? 'text-embedding-3-small',
			'apikey' => $this->resolver->resolveValue($this->apikeyConfig),
			'endpoint' => $this->resolver->resolveValue($this->endpointConfig) ?? 'https://api.openai.com/v1/embeddings'
		];

		$this->configureProvider();
	}

	public function getOptions(): array {
		return $this->resolvedOptions;
	}

	public function setOptions(array $options): void {
		$this->resolvedOptions = array_merge($this->resolvedOptions, $options);
		$this->configureProvider();
	}

	public function embed(array $texts): array {
		return $this->embedResult($texts)->getEmbeddings();
	}

	public function embedResult(array $texts): AiEmbeddingResult {
		$startedAt = microtime(true);

		if(empty($texts)) {
			return new AiEmbeddingResult(
				[],
				AiResultNormalizer::aggregateMetadata('embedding', [], [
					'provider' => OpenAiTransport::getName(),
					'model' => (string)($this->resolvedOptions['model'] ?? ''),
					'adapter' => static::getName(),
					'usage_metrics' => ['input_items' => 0, 'output_vectors' => 0]
				], $startedAt),
				[]
			);
		}

		$model = $this->resolvedOptions['model'] ?? 'text-embedding-3-small';

		$result = $this->getProvider()->request('/v1/embeddings', [
			'model' => $model,
			'input' => $texts
		]);

		if(!isset($result['data']) || !is_array($result['data'])) {
			throw new \RuntimeException('Malformed OpenAI embedding response.');
		}

		$embeddings = array_map(function($item): array {
			if(!is_array($item)) {
				return [];
			}

			return is_array($item['embedding'] ?? null) ? $item['embedding'] : [];
		}, $result['data']);

		return new AiEmbeddingResult(
			$embeddings,
			AiResultNormalizer::metadata('embedding', $result, [
				'provider' => OpenAiTransport::getName(),
				'model' => (string)$model,
				'adapter' => static::getName(),
				'usage_metrics' => [
					'input_items' => count($texts),
					'output_vectors' => count($embeddings)
				]
			], $startedAt),
			$result
		);
	}

	private function getProvider(): OpenAiTransport {
		if($this->provider instanceof OpenAiTransport) {
			return $this->provider;
		}

		$provider = $this->classMap->getInstanceByInterfaceName(IAiProvider::class, OpenAiTransport::getName());

		if(!$provider instanceof OpenAiTransport) {
			throw new \RuntimeException(
				'Unable to resolve provider "' . OpenAiTransport::getName() . '" for interface ' . IAiProvider::class . '.'
			);
		}

		$this->provider = $provider;
		$this->configureProvider();

		return $this->provider;
	}

	private function configureProvider(): void {
		if(!$this->provider instanceof OpenAiTransport) {
			return;
		}

		$this->provider->setOptions([
			'endpoint' => $this->resolvedOptions['endpoint'] ?? 'https://api.openai.com/v1/embeddings',
			'apikey' => $this->resolvedOptions['apikey'] ?? null
		]);
	}
}
