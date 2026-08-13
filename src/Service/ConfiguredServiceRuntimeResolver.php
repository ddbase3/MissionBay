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

namespace MissionBay\Service;

use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Api\IAiEmbeddingModel;
use AssistantFoundation\Api\IConfigurableVectorSearch;
use AssistantFoundation\Api\IImageGenerationModel;
use AssistantFoundation\Api\IServiceDriverDefinition;
use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IParserService;
use MissionBay\Api\ISearchService;
use MissionBay\Api\IVectorStoreService;
use MissionBay\Connection\ConnectionConfig;
use RuntimeException;

/**
 * Resolves configured service records through the discoverable driver definition
 * into isolated runtime service instances.
 *
 * This is the single runtime boundary used by configured agent resources and
 * administrative service tests. Persisted and unsaved settings therefore use
 * the same driver resolution and runtime option mapping.
 */
final class ConfiguredServiceRuntimeResolver {

	private const CONNECTION_SETTINGS_GROUP = 'connection';

	public function __construct(
		private readonly ISettingsStore $settingsStore,
		private readonly IClassMap $classMap,
		private readonly IAgentConfigValueResolver $configValueResolver
	) {}

	/**
	 * @return array<int,string>
	 */
	public function listServiceIds(string $settingsGroup, string $serviceType): array {
		$records = $this->settingsStore->getGroup($settingsGroup);
		$result = [];

		foreach($records as $id => $settings) {
			if(!is_string($id) || $id === '' || !is_array($settings)) {
				continue;
			}

			$config = ServiceConfig::fromSettings($id, $settings);
			if(!$config->isEnabled() || $config->getServiceType() !== $serviceType) {
				continue;
			}

			$serviceId = $this->normalizeKey($config->getId());
			if($serviceId !== '') {
				$result[$serviceId] = $serviceId;
			}
		}

		$result = array_values($result);
		sort($result);

		return $result;
	}

	public function loadServiceConfig(string $settingsGroup, string $serviceId, string $serviceType): ServiceConfig {
		$serviceId = $this->normalizeKey($serviceId);
		if($serviceId === '') {
			throw new RuntimeException('Configured service id is empty.');
		}

		$settings = $this->settingsStore->get($settingsGroup, $serviceId, []);
		if($settings === []) {
			throw new RuntimeException('Service config not found: ' . $settingsGroup . '/' . $serviceId);
		}

		return $this->createServiceConfig($serviceId, $settings, $serviceType, true);
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	public function createServiceConfig(
		string $serviceId,
		array $settings,
		string $serviceType,
		bool $requireEnabled = false
	): ServiceConfig {
		$serviceId = $this->normalizeKey($serviceId);
		if($serviceId === '') {
			throw new RuntimeException('Configured service id is empty.');
		}

		$config = ServiceConfig::fromSettings($serviceId, $settings);

		if($requireEnabled && !$config->isEnabled()) {
			throw new RuntimeException('Service config is disabled: ' . $serviceId . ' ' . $this->formatConfigDebug($config->toSettings()));
		}

		if($config->getServiceType() !== $serviceType) {
			throw new RuntimeException('Service config has wrong service type: ' . $serviceId . ' ' . $this->formatConfigDebug($config->toSettings()));
		}

		if($config->getConnectionId() === '') {
			throw new RuntimeException('Service config has no connection: ' . $serviceId);
		}

		if($config->getDriver() === '') {
			throw new RuntimeException('Service config has no driver: ' . $serviceId);
		}

		return $config;
	}

	public function loadConnectionConfig(string $connectionId): ConnectionConfig {
		$connectionId = $this->normalizeKey($connectionId);
		if($connectionId === '') {
			throw new RuntimeException('Connection id is empty.');
		}

		$settings = $this->settingsStore->get(self::CONNECTION_SETTINGS_GROUP, $connectionId, []);
		if($settings === []) {
			throw new RuntimeException('Connection config not found: ' . self::CONNECTION_SETTINGS_GROUP . '/' . $connectionId);
		}

		$config = ConnectionConfig::fromSettings($connectionId, $settings);
		if(!$config->isEnabled()) {
			throw new RuntimeException('Connection config is disabled: ' . $connectionId . ' ' . $this->formatConfigDebug($config->toSettings()));
		}

		if(trim($config->getBaseUrl()) === '') {
			throw new RuntimeException('Connection config has no base URL: ' . $connectionId . ' ' . $this->formatConfigDebug($config->toSettings()));
		}

		return $config;
	}

	public function resolve(
		string $settingsGroup,
		string $serviceId,
		string $serviceType,
		string $serviceAlias,
		string $implementationInterface,
		array $optionOverrides = []
	): object {
		return $this->resolveServiceConfig(
			$this->loadServiceConfig($settingsGroup, $serviceId, $serviceType),
			$serviceType,
			$serviceAlias,
			$implementationInterface,
			$optionOverrides
		);
	}

	/**
	 * @param array<string,mixed> $settings
	 * @param array<string,mixed> $optionOverrides
	 */
	public function resolveSettings(
		string $serviceId,
		array $settings,
		string $serviceType,
		string $serviceAlias,
		string $implementationInterface,
		array $optionOverrides = []
	): object {
		return $this->resolveServiceConfig(
			$this->createServiceConfig($serviceId, $settings, $serviceType),
			$serviceType,
			$serviceAlias,
			$implementationInterface,
			$optionOverrides
		);
	}

	/**
	 * @param array<string,mixed> $optionOverrides
	 */
	public function resolveServiceConfig(
		ServiceConfig $serviceConfig,
		string $serviceType,
		string $serviceAlias,
		string $implementationInterface,
		array $optionOverrides = []
	): object {
		if($serviceConfig->getServiceType() !== $serviceType) {
			throw new RuntimeException('Service config has wrong service type: ' . $serviceConfig->getId());
		}

		$connectionConfig = $this->loadConnectionConfig($serviceConfig->getConnectionId());
		$driverDefinition = $this->resolveDriverDefinition(
			$serviceConfig->getDriver(),
			$serviceType,
			$implementationInterface
		);
		$service = $this->instantiateDriver($driverDefinition, $implementationInterface);
		$options = $this->buildRuntimeOptions($serviceConfig, $connectionConfig, $serviceAlias, $driverDefinition);
		$options = array_merge($options, $optionOverrides);

		$this->applyRuntimeOptions($service, $implementationInterface, $options);

		return $service;
	}

	public function resolveDriver(
		ServiceConfig $serviceConfig,
		string $serviceType,
		string $implementationInterface
	): object {
		if($serviceConfig->getServiceType() !== $serviceType) {
			throw new RuntimeException('Service config has wrong service type: ' . $serviceConfig->getId());
		}

		return $this->instantiateDriver(
			$this->resolveDriverDefinition(
				$serviceConfig->getDriver(),
				$serviceType,
				$implementationInterface
			),
			$implementationInterface
		);
	}

	public function resolveDriverDefinition(
		string $driver,
		string $serviceType,
		string $implementationInterface
	): IServiceDriverDefinition {
		$driver = $this->normalizeKey($driver);
		$serviceType = $this->normalizeKey($serviceType);
		$implementationInterface = trim($implementationInterface);

		$definitions = $this->classMap->getInstancesByInterface(IServiceDriverDefinition::class);

		foreach($definitions as $definition) {
			if(!$definition instanceof IServiceDriverDefinition) {
				continue;
			}

			if($this->normalizeKey($definition->getServiceType()) !== $serviceType) {
				continue;
			}

			if($this->normalizeKey($definition->getDriver()) !== $driver) {
				continue;
			}

			if(trim($definition->getImplementationInterface()) !== $implementationInterface) {
				continue;
			}

			return $definition;
		}

		throw new RuntimeException(
			'No service driver definition for type "' . $serviceType . '", driver "' . $driver . '" and interface "' . $implementationInterface . '".'
		);
	}

	public function resolveConnectionSecret(ConnectionConfig $connectionConfig): ?string {
		if($this->normalizeKey($connectionConfig->getAuthType()) === 'none') {
			return null;
		}

		$secretConfig = $connectionConfig->getAuthSecretConfig();
		if($secretConfig === []) {
			throw new RuntimeException('Connection config has no secret config: ' . $connectionConfig->getId());
		}

		$value = $this->configValueResolver->resolveValue($secretConfig);
		if(!is_scalar($value) || trim((string)$value) === '') {
			throw new RuntimeException('Connection secret could not be resolved: ' . $connectionConfig->getId());
		}

		return trim((string)$value);
	}

	private function instantiateDriver(IServiceDriverDefinition $driverDefinition, string $implementationInterface): object {
		$implementationName = trim($driverDefinition->getImplementationName());
		if($implementationName === '') {
			throw new RuntimeException('Service driver definition has no implementation name: ' . $driverDefinition->getDriver());
		}

		$class = $this->classMap->getClassByInterfaceName($implementationInterface, $implementationName);
		$service = is_string($class) && $class !== '' ? $this->classMap->instantiate($class) : null;

		if(!is_object($service) || !$service instanceof $implementationInterface) {
			throw new RuntimeException(
				'Unable to resolve service implementation "' . $implementationName . '" for driver "' . $driverDefinition->getDriver() . '".'
			);
		}

		return $service;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function buildRuntimeOptions(
		ServiceConfig $serviceConfig,
		ConnectionConfig $connectionConfig,
		string $serviceAlias,
		IServiceDriverDefinition $driverDefinition
	): array {
		$model = trim($serviceConfig->getModel());
		if($model === '') {
			throw new RuntimeException('Service config has no model: ' . $serviceConfig->getId() . ' ' . $this->formatConfigDebug($serviceConfig->toSettings()));
		}

		$baseUrl = trim($connectionConfig->getBaseUrl());
		$options = [
			$serviceAlias . '_id' => $serviceConfig->getId(),
			$serviceAlias . '_label' => $serviceConfig->getName(),
			'service_type' => $serviceConfig->getServiceType(),
			'service_driver' => $serviceConfig->getDriver(),
			'connection_id' => $connectionConfig->getId(),
			'connection_label' => $connectionConfig->getConnectionName(),
			'connection_type' => $connectionConfig->getType(),
			'connection_driver' => $connectionConfig->getDriver(),
			'auth_type' => $connectionConfig->getAuthType(),
			'auth_header_name' => $connectionConfig->getAuthHeaderName(),
			'model' => $model,
			'endpoint' => $baseUrl,
			'base_url' => $baseUrl,
			'timeout_seconds' => $connectionConfig->getTimeoutSeconds()
		];

		$secret = $this->resolveConnectionSecret($connectionConfig);
		if($secret !== null) {
			$options['apikey'] = $secret;
			$options['auth_secret'] = $secret;
		}

		$serviceOptions = $serviceConfig->getOptions();
		$options = $this->mergeServiceOptions(
			$options,
			$serviceOptions,
			$this->getProtectedServiceOptionKeys($serviceConfig->getServiceType(), $serviceAlias)
		);

		$this->mapServiceOptions($options, $serviceOptions, $serviceConfig->getServiceType(), $driverDefinition);

		return $options;
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 * @param array<string,mixed> $serviceOptions
	 */
	private function mapServiceOptions(
		array &$runtimeOptions,
		array $serviceOptions,
		string $serviceType,
		IServiceDriverDefinition $driverDefinition
	): void {
		switch($serviceType) {
			case 'llm':
				$this->mapOptionalNumber($runtimeOptions, $serviceOptions, 'temperature', 'temperature', 'float');
				$this->mapOptionalNumber($runtimeOptions, $serviceOptions, 'maxTokens', 'max_tokens', 'int');
				$this->mapOptionalNumber($runtimeOptions, $serviceOptions, 'topP', 'top_p', 'float');
				$this->mapOptionalNumber($runtimeOptions, $serviceOptions, 'timeoutSeconds', 'timeout_seconds', 'int');
				$this->mapOptionalNumber($runtimeOptions, $serviceOptions, 'connectTimeoutSeconds', 'connect_timeout_seconds', 'int');
				break;

			case 'embedding':
				$this->mapOptionalNumber($runtimeOptions, $serviceOptions, 'dimensions', 'dimensions', 'int');
				$this->mapOptionalNumber($runtimeOptions, $serviceOptions, 'batchSize', 'batch_size', 'int');
				$this->mapOptionalNumber($runtimeOptions, $serviceOptions, 'timeoutSeconds', 'timeout_seconds', 'int');
				$this->mapOptionalNumber($runtimeOptions, $serviceOptions, 'connectTimeoutSeconds', 'connect_timeout_seconds', 'int');
				$this->mapOptionalBool($runtimeOptions, $serviceOptions, 'normalizeVectors', 'normalize_vectors');
				break;

			case 'image':
				$this->mapOptionalNumber($runtimeOptions, $serviceOptions, 'timeoutSeconds', 'timeout_seconds', 'int');
				$this->mapOptionalNumber($runtimeOptions, $serviceOptions, 'connectTimeoutSeconds', 'connect_timeout_seconds', 'int');
				break;

			case 'search':
				$this->mapOptionalNumber($runtimeOptions, $serviceOptions, 'maxResults', 'max_results', 'int');
				$this->mapOptionalNumber($runtimeOptions, $serviceOptions, 'timeoutSeconds', 'timeout_seconds', 'int');
				$this->mapOptionalNumber($runtimeOptions, $serviceOptions, 'connectTimeoutSeconds', 'connect_timeout_seconds', 'int');
				$this->mapOptionalBool($runtimeOptions, $serviceOptions, 'externalWebAccess', 'external_web_access');
				$this->mapOptionalString($runtimeOptions, $serviceOptions, 'searchContextSize', 'search_context_size');
				$this->mapOptionalString($runtimeOptions, $serviceOptions, 'returnTokenBudget', 'return_token_budget');
				$this->mapOptionalString($runtimeOptions, $serviceOptions, 'toolChoice', 'tool_choice');
				$this->mapOptionalArray($runtimeOptions, $serviceOptions, 'allowedDomains', 'allowed_domains');
				$this->mapOptionalArray($runtimeOptions, $serviceOptions, 'blockedDomains', 'blocked_domains');
				break;

			case 'vectorstore':
				$this->mapOptionalBool($runtimeOptions, $serviceOptions, 'createPayloadIndexes', 'create_payload_indexes');
				$this->mapOptionalNumber($runtimeOptions, $serviceOptions, 'timeoutSeconds', 'timeout_seconds', 'int');
				$this->mapOptionalNumber($runtimeOptions, $serviceOptions, 'connectTimeoutSeconds', 'connect_timeout_seconds', 'int');
				break;

			case 'parser':
				$this->mapOptionalNumber($runtimeOptions, $serviceOptions, 'priority', 'priority', 'int');
				$this->mapOptionalNumber($runtimeOptions, $serviceOptions, 'timeoutSeconds', 'timeout_seconds', 'int');
				$this->mapOptionalNumber($runtimeOptions, $serviceOptions, 'connectTimeoutSeconds', 'connect_timeout_seconds', 'int');
				$this->mapOptionalNumber($runtimeOptions, $serviceOptions, 'maxBytes', 'max_bytes', 'int');
				$this->mapOptionalString($runtimeOptions, $serviceOptions, 'contentType', 'content_type');
				$this->mapOptionalString($runtimeOptions, $serviceOptions, 'fileField', 'file_field');
				$this->mapOptionalString($runtimeOptions, $serviceOptions, 'convertPath', 'convert_path');
				$this->mapOptionalArray($runtimeOptions, $serviceOptions, 'supportedTypes', 'supported_types', true);
				$this->mapOptionalArray($runtimeOptions, $serviceOptions, 'supportedExtensions', 'supported_extensions', true);
				break;
		}

		$this->mapSchemaRuntimeOptions($runtimeOptions, $serviceOptions, $driverDefinition);
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 * @param array<string,mixed> $serviceOptions
	 */
	private function mapSchemaRuntimeOptions(
		array &$runtimeOptions,
		array $serviceOptions,
		IServiceDriverDefinition $driverDefinition
	): void {
		$schema = $driverDefinition->getConfigSchema();
		$properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];

		foreach($properties as $sourceKey => $property) {
			if(!is_string($sourceKey) || !is_array($property) || $sourceKey === 'model') {
				continue;
			}

			$targetKey = trim((string)($property['runtimeKey'] ?? ''));
			if($targetKey === '' || !array_key_exists($sourceKey, $serviceOptions)) {
				continue;
			}

			$this->mapSchemaOption($runtimeOptions, $serviceOptions[$sourceKey], $targetKey, $property);
		}
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 * @param array<string,mixed> $property
	 */
	private function mapSchemaOption(array &$runtimeOptions, mixed $value, string $targetKey, array $property): void {
		$type = strtolower(trim((string)($property['type'] ?? 'string')));

		if($type === 'integer' || $type === 'number') {
			if($value === null || $value === '' || !is_numeric($value)) {
				return;
			}

			$runtimeOptions[$targetKey] = $type === 'integer' ? (int)$value : (float)$value;
			return;
		}

		if($type === 'boolean') {
			$runtimeOptions[$targetKey] = $this->toBool($value, false);
			return;
		}

		if(is_array($value) || is_object($value)) {
			$runtimeOptions[$targetKey] = $value;
			return;
		}

		$value = trim((string)$value);
		if($value !== '') {
			$runtimeOptions[$targetKey] = $value;
		}
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 */
	private function applyRuntimeOptions(object $service, string $implementationInterface, array $runtimeOptions): void {
		if($implementationInterface === IAiChatModel::class && $service instanceof IAiChatModel) {
			$service->setOptions($runtimeOptions);
			return;
		}

		if($implementationInterface === IAiEmbeddingModel::class && $service instanceof IAiEmbeddingModel) {
			$service->setOptions($runtimeOptions);
			return;
		}

		if($implementationInterface === IImageGenerationModel::class && $service instanceof IImageGenerationModel) {
			$service->setOptions($runtimeOptions);
			return;
		}

		if($implementationInterface === IConfigurableVectorSearch::class && $service instanceof IConfigurableVectorSearch) {
			$service->setOptions($runtimeOptions);
			return;
		}

		if($implementationInterface === ISearchService::class && $service instanceof ISearchService) {
			$service->setOptions($runtimeOptions);
			return;
		}

		if($implementationInterface === IVectorStoreService::class && $service instanceof IVectorStoreService) {
			$service->setOptions($runtimeOptions);
			return;
		}

		if($implementationInterface === IParserService::class && $service instanceof IParserService) {
			$service->setOptions($runtimeOptions);
			return;
		}

		throw new RuntimeException('Unsupported configurable service interface: ' . $implementationInterface);
	}

	/**
	 * @return array<string,bool>
	 */
	private function getProtectedServiceOptionKeys(string $serviceType, string $serviceAlias): array {
		$base = [
			$serviceAlias . '_id' => true,
			$serviceAlias . '_label' => true,
			'service_type' => true,
			'service_driver' => true,
			'connection_id' => true,
			'connection_label' => true,
			'connection_type' => true,
			'connection_driver' => true,
			'model' => true,
			'endpoint' => true,
			'apikey' => true
		];

		switch($serviceType) {
			case 'llm':
				return $base + [
					'max_tokens' => true,
					'top_p' => true,
					'timeout_seconds' => true,
					'connect_timeout_seconds' => true,
					'maxtokens' => true
				];

			case 'embedding':
				return $base;

			case 'image':
				return $base + [
					'base_url' => true,
					'auth_type' => true,
					'auth_header_name' => true,
					'auth_secret' => true,
					'timeout_seconds' => true,
					'connect_timeout_seconds' => true,
					'timeoutSeconds' => true,
					'connectTimeoutSeconds' => true
				];

			case 'vectorsearch':
				return $base + [
					'auth_type' => true,
					'auth_header_name' => true,
					'base_url' => true,
					'auth_secret' => true,
					'timeout_seconds' => true,
					'connect_timeout_seconds' => true
				];

			case 'search':
			case 'vectorstore':
			case 'parser':
				return $base + [
					'auth_type' => true,
					'auth_header_name' => true,
					'base_url' => true,
					'auth_secret' => true
				];
		}

		return $base + [
			'auth_type' => true,
			'auth_header_name' => true,
			'base_url' => true,
			'auth_secret' => true
		];
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 * @param array<string,mixed> $serviceOptions
	 * @param array<string,bool> $protected
	 * @return array<string,mixed>
	 */
	private function mergeServiceOptions(array $runtimeOptions, array $serviceOptions, array $protected): array {
		foreach($serviceOptions as $key => $value) {
			if(!is_string($key) || isset($protected[$key])) {
				continue;
			}

			$runtimeOptions[$key] = $value;
		}

		return $runtimeOptions;
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 * @param array<string,mixed> $sourceOptions
	 */
	private function mapOptionalNumber(
		array &$runtimeOptions,
		array $sourceOptions,
		string $sourceKey,
		string $targetKey,
		string $type
	): void {
		if(!array_key_exists($sourceKey, $sourceOptions)) {
			return;
		}

		$value = $sourceOptions[$sourceKey];
		if($value === null || $value === '' || !is_numeric($value)) {
			return;
		}

		$runtimeOptions[$targetKey] = $type === 'int' ? (int)$value : (float)$value;
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 * @param array<string,mixed> $sourceOptions
	 */
	private function mapOptionalBool(array &$runtimeOptions, array $sourceOptions, string $sourceKey, string $targetKey): void {
		if(array_key_exists($sourceKey, $sourceOptions)) {
			$runtimeOptions[$targetKey] = $this->toBool($sourceOptions[$sourceKey], false);
		}
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
		if($value !== '') {
			$runtimeOptions[$targetKey] = $value;
		}
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 * @param array<string,mixed> $sourceOptions
	 */
	private function mapOptionalArray(
		array &$runtimeOptions,
		array $sourceOptions,
		string $sourceKey,
		string $targetKey,
		bool $lowercase = false
	): void {
		if(!array_key_exists($sourceKey, $sourceOptions)) {
			return;
		}

		$value = $sourceOptions[$sourceKey];
		if(is_string($value)) {
			$value = preg_split('/[\r\n,]+/', $value) ?: [];
		}

		if(!is_array($value)) {
			return;
		}

		$out = [];
		foreach($value as $item) {
			$item = trim((string)$item);
			if($lowercase) {
				$item = strtolower($item);
			}
			if($item !== '') {
				$out[] = $item;
			}
		}

		if($out !== []) {
			$runtimeOptions[$targetKey] = array_values(array_unique($out));
		}
	}

	/**
	 * @param array<string,mixed> $config
	 */
	private function formatConfigDebug(array $config): string {
		$json = json_encode($this->redactSensitiveConfig($config), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		return is_string($json) ? $json : '(debug unavailable)';
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array<string,mixed>
	 */
	private function redactSensitiveConfig(array $config): array {
		$out = [];

		foreach($config as $key => $value) {
			$keyString = strtolower((string)$key);
			if(in_array($keyString, [
				'apikey',
				'api_key',
				'auth_secret',
				'secretvalue',
				'secret_value',
				'token',
				'secret',
				'password',
				'authorization'
			], true)) {
				$out[$key] = '[redacted]';
				continue;
			}

			$out[$key] = is_array($value) ? $this->redactSensitiveConfig($value) : $value;
		}

		return $out;
	}

	private function normalizeKey(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}

	private function toBool(mixed $value, bool $default): bool {
		if($value === null || $value === '') {
			return $default;
		}

		if(is_bool($value)) {
			return $value;
		}

		if(is_int($value)) {
			return $value !== 0;
		}

		$value = strtolower(trim((string)$value));
		if(in_array($value, ['1', 'true', 'yes', 'on'], true)) {
			return true;
		}
		if(in_array($value, ['0', 'false', 'no', 'off'], true)) {
			return false;
		}

		return $default;
	}
}
