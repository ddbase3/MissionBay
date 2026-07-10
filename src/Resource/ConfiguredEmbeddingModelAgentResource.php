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
use AssistantFoundation\Dto\AiEmbeddingResult;
use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Connection\ConnectionConfig;
use MissionBay\EmbeddingModel\OpenAiCompatibleEmbeddingModel;
use MissionBay\EmbeddingModel\OpenAiEmbeddingModel;
use MissionBay\Service\ServiceConfig;
use RuntimeException;

/**
 * ConfiguredEmbeddingModelAgentResource
 *
 * Loads a configured embedding service and delegates to the matching
 * IAiEmbeddingModel adapter.
 */
class ConfiguredEmbeddingModelAgentResource extends AbstractConfiguredServiceAgentResource implements IAiEmbeddingModel {

	private const EMBEDDING_SETTINGS_GROUP = 'service-embedding';
	private const CONNECTION_SETTINGS_GROUP = 'connection';
	private const SERVICE_TYPE = 'embedding';
	private const SERVICE_ALIAS = 'embedding';

	private ?IAiEmbeddingModel $model = null;

	public function __construct(
		IAgentConfigValueResolver $resolver,
		ISettingsStore $settingsStore,
		private readonly IClassMap $classMap,
		?string $id = null
	) {
		parent::__construct($resolver, $settingsStore, $id);
	}

	public static function getName(): string {
		return 'configuredembeddingmodelagentresource';
	}

	public function getDescription(): string {
		return 'Loads a configured embedding service by id and delegates to the matching IAiEmbeddingModel adapter.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->setServiceConfigFromResourceConfig($config);
		$this->model = null;
		$this->resolvedOptions = [];

		$this->configureModel();
	}

	public function embed(array $texts): array {
		return $this->ensureModel()->embed($texts);
	}

	public function embedResult(array $texts): AiEmbeddingResult {
		return $this->ensureModel()->embedResult($texts);
	}

	protected function ensureConfigured(): void {
		$this->ensureModel();
	}

	protected function applyResolvedOptions(): void {
		if($this->model instanceof IAiEmbeddingModel) {
			$this->model->setOptions($this->resolvedOptions);
		}
	}

	private function ensureModel(): IAiEmbeddingModel {
		if($this->model instanceof IAiEmbeddingModel) {
			return $this->model;
		}

		$this->configureModel();

		if(!$this->model instanceof IAiEmbeddingModel) {
			throw new RuntimeException('Configured embedding model could not be initialized.');
		}

		return $this->model;
	}

	private function configureModel(): void {
		$serviceId = $this->resolveServiceId();

		if($serviceId === '') {
			throw new RuntimeException('ConfiguredEmbeddingModelAgentResource requires config key "service".');
		}

		$serviceConfig = $this->loadServiceConfig(self::EMBEDDING_SETTINGS_GROUP, $serviceId, self::SERVICE_TYPE);
		$connectionConfig = $this->loadConnectionConfig(self::CONNECTION_SETTINGS_GROUP, $serviceConfig->getConnectionId());

		$modelName = $this->resolveEmbeddingModelName($serviceConfig->getDriver());

		if($modelName === '') {
			throw new RuntimeException(
				'Embedding service config has no usable driver: ' . $serviceId . ' ' . $this->formatConfigDebug($serviceConfig->toSettings())
			);
		}

		$model = $this->classMap->getInstanceByInterfaceName(IAiEmbeddingModel::class, $modelName);

		if(!$model instanceof IAiEmbeddingModel) {
			throw new RuntimeException(
				'Unable to resolve embedding model "' . $modelName . '" for driver "' . $serviceConfig->getDriver() . '".'
			);
		}

		$this->resolvedOptions = $this->buildRuntimeOptions($serviceConfig, $connectionConfig);
		$this->resolvedOptions = array_merge($this->resolvedOptions, $this->optionOverrides);

		$this->model = $model;
		$this->applyResolvedOptions();
	}

	private function resolveEmbeddingModelName(string $driver): string {
		$map = [
			'openai-embedding' => OpenAiEmbeddingModel::getName(),
			'openai-compatible-embedding' => OpenAiCompatibleEmbeddingModel::getName()
		];

		return $map[$driver] ?? '';
	}

	/**
	 * @return array<string,mixed>
	 */
	private function buildRuntimeOptions(ServiceConfig $serviceConfig, ConnectionConfig $connectionConfig): array {
		$options = $this->buildBaseRuntimeOptions($serviceConfig, $connectionConfig, self::SERVICE_ALIAS);
		$serviceOptions = $serviceConfig->getOptions();

		$options = $this->mergeServiceOptions($options, $serviceOptions, [
			'embedding_id' => true,
			'embedding_label' => true,
			'service_type' => true,
			'service_driver' => true,
			'connection_id' => true,
			'connection_label' => true,
			'connection_type' => true,
			'connection_driver' => true,
			'model' => true,
			'endpoint' => true,
			'apikey' => true
		]);

		$this->mapOptionalNumber($options, $serviceOptions, 'dimensions', 'dimensions', 'int');
		$this->mapOptionalNumber($options, $serviceOptions, 'batchSize', 'batch_size', 'int');
		$this->mapOptionalNumber($options, $serviceOptions, 'timeoutSeconds', 'timeout_seconds', 'int');
		$this->mapOptionalNumber($options, $serviceOptions, 'connectTimeoutSeconds', 'connect_timeout_seconds', 'int');
		$this->mapOptionalBool($options, $serviceOptions, 'normalizeVectors', 'normalize_vectors');

		return $options;
	}
}
