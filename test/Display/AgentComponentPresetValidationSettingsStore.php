<?php declare(strict_types=1);

namespace MissionBay\Test\Display;

use Base3\Settings\Api\ISettingsStore;
use MissionBay\Display\AgentComponentPresetAdminDisplay;
use PHPUnit\Framework\TestCase;

final class AgentComponentPresetValidationSettingsStore implements ISettingsStore {

	/** @param array<string,array<string,mixed>> $data */
	public function __construct(private array $data) {}

	public function get(string $group, string $name, array $default = []): array {
		return $this->data[$name] ?? $default;
	}

	public function set(string $group, string $name, array $settings): void {
		$this->data[$name] = $settings;
	}

	public function has(string $group, string $name): bool {
		return array_key_exists($name, $this->data);
	}

	public function remove(string $group, string $name): void {
		unset($this->data[$name]);
	}

	public function getGroup(string $group): array {
		return $this->data;
	}

	public function save(): void {}

	public function reload(): void {}
}
