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

namespace MissionBay\Connection;

final class ConnectionConfig {

	private readonly string $id;
	private readonly string $name;
	private readonly string $type;
	private readonly string $driver;
	private readonly string $baseUrl;
	private readonly string $authType;

	/**
	 * ConfigValue resolver definition for the authentication secret.
	 *
	 * @var array<string,mixed>
	 */
	private readonly array $authSecretConfig;

	private readonly int $timeoutSeconds;
	private readonly string $scope;
	private readonly bool $enabled;

	/**
	 * @var array<string,mixed>
	 */
	private readonly array $options;

	private readonly string $authHeaderName;

	/**
	 * The constructor keeps the old signature intentionally.
	 *
	 * Existing callers still pass secretMode and secretValue. New callers should
	 * use createWithAuthSecretConfig() once the displays and runtime builders are
	 * migrated.
	 *
	 * @param array<string,mixed> $options
	 * @param array<string,mixed> $authSecretConfig
	 */
	public function __construct(
		string $id,
		string $name,
		string $type,
		string $driver,
		string $baseUrl,
		string $authType,
		string $secretMode,
		string $secretValue,
		int $timeoutSeconds,
		string $scope,
		bool $enabled,
		array $options = [],
		string $authHeaderName = '',
		array $authSecretConfig = []
	) {
		$this->id = self::normalizeKey($id);
		$this->name = trim($name);
		$this->type = self::normalizeKey($type);
		$this->driver = self::normalizeKey($driver);
		$this->baseUrl = trim($baseUrl);
		$this->authType = self::normalizeAuthType($authType);
		$this->authHeaderName = trim($authHeaderName);
		$this->timeoutSeconds = self::normalizePositiveInt($timeoutSeconds, 60);
		$this->scope = self::normalizeScope($scope);
		$this->enabled = $enabled;
		$this->options = $options;

		if($this->authType === 'none') {
			$this->authSecretConfig = [];
			return;
		}

		if($authSecretConfig !== []) {
			$this->authSecretConfig = self::normalizeAuthSecretConfig($authSecretConfig);
			return;
		}

		$this->authSecretConfig = self::buildLegacyAuthSecretConfig($secretMode, $secretValue);
	}

	/**
	 * @param array<string,mixed> $authSecretConfig
	 * @param array<string,mixed> $options
	 */
	public static function createWithAuthSecretConfig(
		string $id,
		string $name,
		string $type,
		string $driver,
		string $baseUrl,
		string $authType,
		array $authSecretConfig,
		int $timeoutSeconds,
		string $scope,
		bool $enabled,
		array $options = [],
		string $authHeaderName = ''
	): self {
		return new self(
			$id,
			$name,
			$type,
			$driver,
			$baseUrl,
			$authType,
			'fixed',
			'',
			$timeoutSeconds,
			$scope,
			$enabled,
			$options,
			$authHeaderName,
			$authSecretConfig
		);
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	public static function fromSettings(string $id, array $settings): self {
		$id = self::normalizeKey($settings['id'] ?? $id);
		$name = trim((string)($settings['name'] ?? ''));
		$type = self::normalizeKey($settings['type'] ?? 'http');
		$driver = self::normalizeKey($settings['driver'] ?? $type);
		$baseUrl = trim((string)($settings['baseUrl'] ?? ''));
		$auth = is_array($settings['auth'] ?? null) ? $settings['auth'] : [];
		$authType = self::normalizeAuthType($auth['type'] ?? 'none');
		$authHeaderName = trim((string)($auth['headerName'] ?? ''));
		$timeoutSeconds = self::normalizePositiveInt($settings['timeoutSeconds'] ?? 60, 60);
		$scope = self::normalizeScope((string)($settings['scope'] ?? 'global'));
		$enabled = self::normalizeBool($settings['enabled'] ?? true, true);
		$options = is_array($settings['options'] ?? null) ? $settings['options'] : [];

		if($name === '') {
			$name = $id;
		}

		if($type === '') {
			$type = 'http';
		}

		if($driver === '') {
			$driver = $type;
		}

		if($authType === 'none') {
			return new self(
				$id,
				$name,
				$type,
				$driver,
				$baseUrl,
				$authType,
				'fixed',
				'',
				$timeoutSeconds,
				$scope,
				$enabled,
				$options,
				''
			);
		}

		$authSecretConfig = [];

		if(is_array($auth['secret'] ?? null)) {
			$authSecretConfig = self::normalizeAuthSecretConfig($auth['secret']);
		}
		else {
			$authSecretConfig = self::buildLegacyAuthSecretConfig(
				(string)($auth['secretMode'] ?? 'fixed'),
				(string)($auth['secretValue'] ?? '')
			);
		}

		return self::createWithAuthSecretConfig(
			$id,
			$name,
			$type,
			$driver,
			$baseUrl,
			$authType,
			$authSecretConfig,
			$timeoutSeconds,
			$scope,
			$enabled,
			$options,
			$authHeaderName
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function toSettings(): array {
		$auth = [
			'type' => $this->authType
		];

		if($this->authHeaderName !== '') {
			$auth['headerName'] = $this->authHeaderName;
		}

		if($this->authType !== 'none' && $this->authSecretConfig !== []) {
			$auth['secret'] = $this->authSecretConfig;
		}

		return [
			'id' => $this->id,
			'name' => $this->name,
			'type' => $this->type,
			'driver' => $this->driver,
			'baseUrl' => $this->baseUrl,
			'auth' => $auth,
			'timeoutSeconds' => $this->timeoutSeconds,
			'scope' => $this->scope,
			'enabled' => $this->enabled,
			'options' => $this->options
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	public function toDisplayArray(): array {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'type' => $this->type,
			'driver' => $this->driver,
			'baseUrl' => $this->baseUrl,
			'authType' => $this->authType,
			'secretMode' => $this->getLegacySecretMode(),
			'secretValue' => $this->getLegacySecretValue(),
			'authSecretMode' => $this->getAuthSecretMode(),
			'authSecretSummary' => $this->getAuthSecretSummary(),
			'authSecretConfigured' => $this->hasAuthSecret(),
			'authSecretConfig' => $this->getSafeAuthSecretConfig(),
			'authHeaderName' => $this->authHeaderName,
			'timeoutSeconds' => $this->timeoutSeconds,
			'scope' => $this->scope,
			'enabled' => $this->enabled,
			'options' => $this->options
		];
	}

	public function getId(): string {
		return $this->id;
	}

	public function getConnectionName(): string {
		return $this->name;
	}

	public function getType(): string {
		return $this->type;
	}

	public function getDriver(): string {
		return $this->driver;
	}

	public function getBaseUrl(): string {
		return $this->baseUrl;
	}

	public function getAuthType(): string {
		return $this->authType;
	}

	public function getAuthHeaderName(): string {
		return $this->authHeaderName;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getAuthSecretConfig(): array {
		return $this->authSecretConfig;
	}

	public function getAuthSecretMode(): string {
		if($this->authSecretConfig === []) {
			return '';
		}

		return self::normalizeKey($this->authSecretConfig['mode'] ?? 'fixed');
	}

	public function hasAuthSecret(): bool {
		if($this->authType === 'none' || $this->authSecretConfig === []) {
			return false;
		}

		$mode = $this->getAuthSecretMode();

		return match($mode) {
			'env' => trim((string)($this->authSecretConfig['name'] ?? $this->authSecretConfig['value'] ?? '')) !== '',
			'configuration' => trim((string)($this->authSecretConfig['group'] ?? $this->authSecretConfig['section'] ?? '')) !== ''
				&& trim((string)($this->authSecretConfig['key'] ?? '')) !== '',
			'config' => trim((string)($this->authSecretConfig['group'] ?? $this->authSecretConfig['section'] ?? '')) !== ''
				&& trim((string)($this->authSecretConfig['key'] ?? '')) !== '',
			'file' => trim((string)($this->authSecretConfig['path'] ?? '')) !== '',
			'fixed' => trim((string)($this->authSecretConfig['value'] ?? '')) !== '',
			default => count($this->authSecretConfig) > 1,
		};
	}

	public function getAuthSecretSummary(): string {
		if(!$this->hasAuthSecret()) {
			return '';
		}

		$mode = $this->getAuthSecretMode();

		return match($mode) {
			'env' => 'env: ' . (string)($this->authSecretConfig['name'] ?? $this->authSecretConfig['value'] ?? ''),
			'configuration' => 'configuration: ' . (string)($this->authSecretConfig['group'] ?? $this->authSecretConfig['section'] ?? '') . '/' . (string)($this->authSecretConfig['key'] ?? ''),
			'config' => 'config: ' . (string)($this->authSecretConfig['group'] ?? $this->authSecretConfig['section'] ?? '') . '/' . (string)($this->authSecretConfig['key'] ?? ''),
			'file' => 'file: ' . (string)($this->authSecretConfig['path'] ?? ''),
			'fixed' => 'stored value',
			default => $mode,
		};
	}

	public function getTimeoutSeconds(): int {
		return $this->timeoutSeconds;
	}

	public function getScope(): string {
		return $this->scope;
	}

	public function isEnabled(): bool {
		return $this->enabled;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getOptions(): array {
		return $this->options;
	}

	private function getLegacySecretMode(): string {
		$mode = $this->getAuthSecretMode();

		if($mode === '') {
			return 'fixed';
		}

		if($mode === 'env') {
			return 'env';
		}

		return 'fixed';
	}

	private function getLegacySecretValue(): string {
		$mode = $this->getAuthSecretMode();

		if($mode === 'env') {
			return trim((string)($this->authSecretConfig['name'] ?? $this->authSecretConfig['value'] ?? ''));
		}

		return '';
	}

	/**
	 * @return array<string,mixed>
	 */
	private function getSafeAuthSecretConfig(): array {
		if($this->authSecretConfig === []) {
			return [];
		}

		$config = $this->authSecretConfig;

		if(($config['mode'] ?? '') === 'fixed' && array_key_exists('value', $config)) {
			$config['value'] = '';
			$config['configured'] = $this->hasAuthSecret();
		}

		return $config;
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function buildLegacyAuthSecretConfig(string $secretMode, string $secretValue): array {
		$secretMode = self::normalizeLegacySecretMode($secretMode);
		$secretValue = trim($secretValue);

		if($secretValue === '') {
			return [];
		}

		if($secretMode === 'env') {
			return [
				'mode' => 'env',
				'name' => $secretValue
			];
		}

		return [
			'mode' => 'fixed',
			'value' => $secretValue
		];
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array<string,mixed>
	 */
	private static function normalizeAuthSecretConfig(array $config): array {
		if($config === []) {
			return [];
		}

		$mode = self::normalizeKey($config['mode'] ?? 'fixed');

		if($mode === 'config') {
			$mode = 'configuration';
		}

		if($mode === '') {
			$mode = 'fixed';
		}

		$config['mode'] = $mode;

		return match($mode) {
			'env' => self::normalizeEnvAuthSecretConfig($config),
			'configuration' => self::normalizeConfigurationAuthSecretConfig($config),
			'file' => self::normalizeFileAuthSecretConfig($config),
			'fixed' => self::normalizeFixedAuthSecretConfig($config),
			default => $config,
		};
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array<string,mixed>
	 */
	private static function normalizeFixedAuthSecretConfig(array $config): array {
		return [
			'mode' => 'fixed',
			'value' => trim((string)($config['value'] ?? ''))
		];
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array<string,mixed>
	 */
	private static function normalizeEnvAuthSecretConfig(array $config): array {
		return [
			'mode' => 'env',
			'name' => trim((string)($config['name'] ?? $config['value'] ?? ''))
		];
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array<string,mixed>
	 */
	private static function normalizeConfigurationAuthSecretConfig(array $config): array {
		return [
			'mode' => 'configuration',
			'group' => trim((string)($config['group'] ?? $config['section'] ?? '')),
			'key' => trim((string)($config['key'] ?? ''))
		];
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array<string,mixed>
	 */
	private static function normalizeFileAuthSecretConfig(array $config): array {
		return [
			'mode' => 'file',
			'path' => trim((string)($config['path'] ?? '')),
			'trim' => self::normalizeBool($config['trim'] ?? true, true)
		];
	}

	private static function normalizeKey(mixed $value): string {
		$value = strtolower(trim((string)$value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}

	private static function normalizeAuthType(mixed $value): string {
		$value = self::normalizeKey($value);

		if(in_array($value, ['none', 'bearer', 'api-key', 'basic'], true)) {
			return $value;
		}

		return 'none';
	}

	private static function normalizeLegacySecretMode(mixed $value): string {
		$value = self::normalizeKey($value);

		if(in_array($value, ['fixed', 'env'], true)) {
			return $value;
		}

		return 'fixed';
	}

	private static function normalizeScope(string $value): string {
		$value = self::normalizeKey($value);

		if($value === '') {
			return 'global';
		}

		return $value;
	}

	private static function normalizePositiveInt(mixed $value, int $default): int {
		if(!is_numeric($value)) {
			return $default;
		}

		$value = (int)$value;

		return $value > 0 ? $value : $default;
	}

	private static function normalizeBool(mixed $value, bool $default): bool {
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
