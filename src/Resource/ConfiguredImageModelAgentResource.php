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

use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IImageGenerationModel;
use MissionBay\Connection\ConnectionConfig;
use MissionBay\ImageModel\OpenAiCompatibleImageModel;
use MissionBay\ImageModel\OpenAiImageModel;
use MissionBay\Service\ServiceConfig;
use RuntimeException;

/**
 * ConfiguredImageModelAgentResource
 *
 * Loads a configured image generation service and delegates to the matching
 * IImageGenerationModel adapter.
 */
class ConfiguredImageModelAgentResource extends AbstractConfiguredServiceAgentResource implements IImageGenerationModel {

	private const IMAGE_SETTINGS_GROUP = 'service-image';
	private const CONNECTION_SETTINGS_GROUP = 'connection';
	private const SERVICE_TYPE = 'image';
	private const SERVICE_ALIAS = 'image';

	private ?IImageGenerationModel $model = null;

	public function __construct(
		IAgentConfigValueResolver $resolver,
		ISettingsStore $settingsStore,
		private readonly IClassMap $classMap,
		?string $id = null
	) {
		parent::__construct($resolver, $settingsStore, $id);
	}

	public static function getName(): string {
		return 'configuredimagemodelagentresource';
	}

	public function getDescription(): string {
		return 'Loads a configured image generation service by id and delegates to the matching image model adapter.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->setServiceConfigFromResourceConfig($config);
		$this->model = null;
		$this->resolvedOptions = [];

		$this->configureModel();
	}

	public function generate(string $prompt, array $options = []): array {
		return $this->ensureModel()->generate($prompt, $options);
	}

	protected function ensureConfigured(): void {
		$this->ensureModel();
	}

	protected function applyResolvedOptions(): void {
		if($this->model instanceof IImageGenerationModel) {
			$this->model->setOptions($this->resolvedOptions);
		}
	}

	private function ensureModel(): IImageGenerationModel {
		if($this->model instanceof IImageGenerationModel) {
			return $this->model;
		}

		$this->configureModel();

		if(!$this->model instanceof IImageGenerationModel) {
			throw new RuntimeException('Configured image generation model could not be initialized.');
		}

		return $this->model;
	}

	private function configureModel(): void {
		$serviceId = $this->resolveServiceId();

		if($serviceId === '') {
			throw new RuntimeException('ConfiguredImageModelAgentResource requires config key "service".');
		}

		$serviceConfig = $this->loadServiceConfig(self::IMAGE_SETTINGS_GROUP, $serviceId, self::SERVICE_TYPE);
		$connectionConfig = $this->loadConnectionConfig(self::CONNECTION_SETTINGS_GROUP, $serviceConfig->getConnectionId());

		$modelName = $this->resolveImageModelName($serviceConfig->getDriver());

		if($modelName === '') {
			throw new RuntimeException(
				'Image service config has no usable driver: ' . $serviceId . ' ' . $this->formatConfigDebug($serviceConfig->toSettings())
			);
		}

		$model = $this->classMap->getInstanceByInterfaceName(IImageGenerationModel::class, $modelName);

		if(!$model instanceof IImageGenerationModel) {
			throw new RuntimeException(
				'Unable to resolve image model "' . $modelName . '" for driver "' . $serviceConfig->getDriver() . '".'
			);
		}

		$this->resolvedOptions = $this->buildRuntimeOptions($serviceConfig, $connectionConfig);
		$this->resolvedOptions = array_merge($this->resolvedOptions, $this->optionOverrides);

		$this->model = $model;
		$this->applyResolvedOptions();
	}

	private function resolveImageModelName(string $driver): string {
		$map = [
			'openai-image' => OpenAiImageModel::getName(),
			'openai-compatible-image' => OpenAiCompatibleImageModel::getName()
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
			'image_id' => true,
			'image_label' => true,
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

		$this->mapOptionalNumber($options, $serviceOptions, 'numberOfImages', 'n', 'int');
		$this->mapOptionalNumber($options, $serviceOptions, 'outputCompression', 'output_compression', 'int');
		$this->mapOptionalNumber($options, $serviceOptions, 'timeoutSeconds', 'timeout_seconds', 'int');
		$this->mapOptionalNumber($options, $serviceOptions, 'connectTimeoutSeconds', 'connect_timeout_seconds', 'int');
		$this->mapOptionalString($options, $serviceOptions, 'size', 'size');
		$this->mapOptionalString($options, $serviceOptions, 'quality', 'quality');
		$this->mapOptionalString($options, $serviceOptions, 'outputFormat', 'output_format');
		$this->mapOptionalString($options, $serviceOptions, 'background', 'background');
		$this->mapOptionalString($options, $serviceOptions, 'moderation', 'moderation');

		return $options;
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 * @param array<string,mixed> $sourceOptions
	 */
	private function mapOptionalString(array &$runtimeOptions, array $sourceOptions, string $sourceKey, string $targetKey): void {
		if(!array_key_exists($sourceKey, $sourceOptions)) {
			return;
		}

		$value = trim((string)$sourceOptions[$sourceKey]);

		if($value === '') {
			return;
		}

		$runtimeOptions[$targetKey] = $value;
	}
}
