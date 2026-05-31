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

use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Connection\ConnectionConfig;
use MissionBay\Service\ServiceConfig;
use RuntimeException;

abstract class AbstractConfiguredServiceAgentResource extends AbstractAgentResource {

	protected array|string|null $serviceConfig = null;

	/**
	 * @var array<string,mixed>
	 */
	protected array $resolvedOptions = [];

	/**
	 * @var array<string,mixed>
	 */
	protected array $optionOverrides = [];

	public function __construct(
		protected readonly IAgentConfigValueResolver $resolver,
		protected readonly ISettingsStore $settingsStore,
		?string $id = null
	) {
		parent::__construct($id);
	}

	public function getOptions(): array {
		$this->ensureConfigured();

		return $this->resolvedOptions;
	}

	/**
	 * @param array<string,mixed> $options
	 */
	public function setOptions(array $options): void {
		$this->optionOverrides = array_merge($this->optionOverrides, $options);
		$this->resolvedOptions = array_merge($this->resolvedOptions, $options);

		$this->applyResolvedOptions();
	}

	abstract protected function ensureConfigured(): void;

	abstract protected function applyResolvedOptions(): void;

	protected function setServiceConfigFromResourceConfig(array $config): void {
		$this->serviceConfig = $config['service'] ?? null;
	}

	protected function resolveServiceId(): string {
		$value = $this->resolver->resolveValue($this->serviceConfig);

		if(!is_scalar($value) && $value !== null) {
			return '';
		}

		return $this->normalizeKey((string)$value);
	}

	protected function loadServiceConfig(string $settingsGroup, string $serviceId, string $expectedServiceType): ServiceConfig {
		$settings = $this->settingsStore->get($settingsGroup, $serviceId, []);

		if($settings === [] || !is_array($settings)) {
			throw new RuntimeException('Service config not found: ' . $settingsGroup . '/' . $serviceId);
		}

		$config = ServiceConfig::fromSettings($serviceId, $settings);

		if(!$config->isEnabled()) {
			throw new RuntimeException('Service config is disabled: ' . $serviceId . ' ' . $this->formatConfigDebug($config->toSettings()));
		}

		if($config->getServiceType() !== $expectedServiceType) {
			throw new RuntimeException('Service config has wrong service type: ' . $serviceId . ' ' . $this->formatConfigDebug($config->toSettings()));
		}

		return $config;
	}

	protected function loadConnectionConfig(string $settingsGroup, string $connectionId): ConnectionConfig {
		$settings = $this->settingsStore->get($settingsGroup, $connectionId, []);

		if($settings === [] || !is_array($settings)) {
			throw new RuntimeException('Connection config not found: ' . $settingsGroup . '/' . $connectionId);
		}

		$config = ConnectionConfig::fromSettings($connectionId, $settings);
		$data = $config->toDisplayArray();

		if(!$this->toBool($data['enabled'] ?? true, true)) {
			throw new RuntimeException('Connection config is disabled: ' . $connectionId . ' ' . $this->formatConfigDebug($data));
		}

		return $config;
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function buildBaseRuntimeOptions(ServiceConfig $serviceConfig, ConnectionConfig $connectionConfig, string $serviceAlias): array {
		$model = trim($serviceConfig->getModel());

		if($model === '') {
			throw new RuntimeException('Service config has no model: ' . $serviceConfig->getId() . ' ' . $this->formatConfigDebug($serviceConfig->toSettings()));
		}

		$connectionData = $connectionConfig->toDisplayArray();
		$baseUrl = trim((string)($connectionData['baseUrl'] ?? ''));

		if($baseUrl === '') {
			throw new RuntimeException('Connection config has no base URL: ' . (string)($connectionData['id'] ?? 'unknown') . ' ' . $this->formatConfigDebug($connectionData));
		}

		$options = [
			$serviceAlias . '_id' => $serviceConfig->getId(),
			$serviceAlias . '_label' => $serviceConfig->getName(),
			'service_type' => $serviceConfig->getServiceType(),
			'service_driver' => $serviceConfig->getDriver(),
			'connection_id' => (string)($connectionData['id'] ?? ''),
			'connection_label' => (string)($connectionData['name'] ?? ''),
			'connection_type' => (string)($connectionData['type'] ?? ''),
			'connection_driver' => (string)($connectionData['driver'] ?? ''),
			'auth_type' => (string)($connectionData['authType'] ?? 'none'),
			'auth_header_name' => trim((string)($connectionData['authHeaderName'] ?? '')),
			'model' => $model,
			'endpoint' => $baseUrl,
			'base_url' => $baseUrl
		];

		$secret = $this->resolveConnectionSecret($connectionData);

		if($secret !== null) {
			$options['apikey'] = $secret;
			$options['auth_secret'] = $secret;
		}

		return $options;
	}

	/**
	 * @param array<string,mixed> $connectionData
	 */
	protected function resolveConnectionSecret(array $connectionData): ?string {
		$authType = $this->normalizeKey((string)($connectionData['authType'] ?? 'none'));

		if($authType === 'none') {
			return null;
		}

		$secretMode = $this->normalizeSecretMode((string)($connectionData['secretMode'] ?? 'fixed'));
		$secretValue = trim((string)($connectionData['secretValue'] ?? ''));

		if($secretValue === '') {
			throw new RuntimeException('Connection config has no secret value: ' . (string)($connectionData['id'] ?? 'unknown'));
		}

		$value = $this->resolver->resolveValue([
			'mode' => $secretMode,
			'value' => $secretValue
		]);

		if($value === null || trim((string)$value) === '') {
			throw new RuntimeException('Connection secret could not be resolved: ' . (string)($connectionData['id'] ?? 'unknown'));
		}

		return (string)$value;
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 * @param array<string,mixed> $serviceOptions
	 * @param array<string,bool> $protected
	 * @return array<string,mixed>
	 */
	protected function mergeServiceOptions(array $runtimeOptions, array $serviceOptions, array $protected): array {
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
	protected function mapOptionalNumber(array &$runtimeOptions, array $sourceOptions, string $sourceKey, string $targetKey, string $type): void {
		if(!array_key_exists($sourceKey, $sourceOptions)) {
			return;
		}

		$value = $sourceOptions[$sourceKey];

		if($value === null || $value === '' || !is_numeric($value)) {
			return;
		}

		if($type === 'int') {
			$runtimeOptions[$targetKey] = (int)$value;
			return;
		}

		$runtimeOptions[$targetKey] = (float)$value;
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 * @param array<string,mixed> $sourceOptions
	 */
	protected function mapOptionalBool(array &$runtimeOptions, array $sourceOptions, string $sourceKey, string $targetKey): void {
		if(!array_key_exists($sourceKey, $sourceOptions)) {
			return;
		}

		$runtimeOptions[$targetKey] = $this->toBool($sourceOptions[$sourceKey], false);
	}

	protected function getIntResolvedOption(string $key, int $default): int {
		$value = $this->resolvedOptions[$key] ?? null;

		if($value === null || $value === '' || !is_numeric($value)) {
			return $default;
		}

		$value = (int)$value;

		return $value > 0 ? $value : $default;
	}

	/**
	 * @param array<string,mixed> $config
	 */
	protected function formatConfigDebug(array $config): string {
		$json = json_encode($this->redactSensitiveConfig($config), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if(!is_string($json)) {
			return '(debug unavailable)';
		}

		return $json;
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array<string,mixed>
	 */
	protected function redactSensitiveConfig(array $config): array {
		$out = [];

		foreach($config as $key => $value) {
			$keyString = strtolower((string)$key);

			if($this->isSensitiveKey($keyString)) {
				$out[$key] = '[redacted]';
				continue;
			}

			if(is_array($value)) {
				$out[$key] = $this->redactSensitiveConfig($value);
				continue;
			}

			$out[$key] = $value;
		}

		return $out;
	}

	protected function isSensitiveKey(string $key): bool {
		return in_array($key, [
			'apikey',
			'api_key',
			'auth_secret',
			'secretvalue',
			'secret_value',
			'token',
			'secret',
			'password',
			'authorization'
		], true);
	}

	protected function normalizeKey(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}

	protected function normalizeSecretMode(string $value): string {
		$value = $this->normalizeKey($value);

		if(in_array($value, ['fixed', 'env'], true)) {
			return $value;
		}

		return 'fixed';
	}

	protected function toBool(mixed $value, bool $default): bool {
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
