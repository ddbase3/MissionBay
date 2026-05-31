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

final class ServiceConfig {

	/**
	 * @param array<string,mixed> $options
	 */
	public function __construct(
		private readonly string $id,
		private readonly string $name,
		private readonly string $serviceType,
		private readonly string $connectionId,
		private readonly string $driver,
		private readonly string $model,
		private readonly bool $enabled,
		private readonly array $options = []
	) {}

	/**
	 * @param array<string,mixed> $settings
	 */
	public static function fromSettings(string $id, array $settings): self {
		$id = self::normalizeKey($settings['id'] ?? $id);
		$name = trim((string)($settings['name'] ?? ''));
		$serviceType = self::normalizeKey($settings['serviceType'] ?? '');
		$connectionId = self::normalizeKey($settings['connection'] ?? '');
		$driver = self::normalizeKey($settings['driver'] ?? '');
		$model = trim((string)($settings['model'] ?? ''));
		$enabled = self::normalizeBool($settings['enabled'] ?? true, true);
		$options = is_array($settings['options'] ?? null) ? $settings['options'] : [];

		if($name === '') {
			$name = $id;
		}

		return new self(
			$id,
			$name,
			$serviceType,
			$connectionId,
			$driver,
			$model,
			$enabled,
			$options
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function toSettings(): array {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'serviceType' => $this->serviceType,
			'connection' => $this->connectionId,
			'driver' => $this->driver,
			'model' => $this->model,
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
			'serviceType' => $this->serviceType,
			'connection' => $this->connectionId,
			'driver' => $this->driver,
			'model' => $this->model,
			'enabled' => $this->enabled,
			'options' => $this->options
		];
	}

	public function getId(): string {
		return $this->id;
	}

	public function getName(): string {
		return $this->name;
	}

	public function getServiceType(): string {
		return $this->serviceType;
	}

	public function getConnectionId(): string {
		return $this->connectionId;
	}

	public function getDriver(): string {
		return $this->driver;
	}

	public function getModel(): string {
		return $this->model;
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

	private static function normalizeKey(mixed $value): string {
		$value = strtolower(trim((string)$value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
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
