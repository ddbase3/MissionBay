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

	/**
	 * @param array<string,mixed> $options
	 */
	public function __construct(
		private readonly string $id,
		private readonly string $name,
		private readonly string $type,
		private readonly string $driver,
		private readonly string $baseUrl,
		private readonly string $authType,
		private readonly string $secretMode,
		private readonly string $secretValue,
		private readonly int $timeoutSeconds,
		private readonly string $scope,
		private readonly bool $enabled,
		private readonly array $options = [],
		private readonly string $authHeaderName = ''
	) {}

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
		$secretMode = self::normalizeSecretMode($auth['secretMode'] ?? 'fixed');
		$secretValue = trim((string)($auth['secretValue'] ?? ''));
		$authHeaderName = trim((string)($auth['headerName'] ?? ''));
		$timeoutSeconds = self::normalizePositiveInt($settings['timeoutSeconds'] ?? 60, 60);
		$scope = self::normalizeKey($settings['scope'] ?? 'global');
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

		if($scope === '') {
			$scope = 'global';
		}

		if($authType === 'none') {
			$secretMode = 'fixed';
			$secretValue = '';
			$authHeaderName = '';
		}

		return new self(
			$id,
			$name,
			$type,
			$driver,
			$baseUrl,
			$authType,
			$secretMode,
			$secretValue,
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
			'type' => $this->authType,
			'secretMode' => $this->secretMode,
			'secretValue' => $this->secretValue
		];

		if($this->authHeaderName !== '') {
			$auth['headerName'] = $this->authHeaderName;
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
			'secretMode' => $this->secretMode,
			'secretValue' => $this->secretValue,
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

	public function getDriver(): string {
		return $this->driver;
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

	private static function normalizeSecretMode(mixed $value): string {
		$value = self::normalizeKey($value);

		if(in_array($value, ['fixed', 'env'], true)) {
			return $value;
		}

		return 'fixed';
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
