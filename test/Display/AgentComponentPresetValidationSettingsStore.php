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

namespace Base3\MissionBay\Test\Display;

use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Display\AgentComponentPresetAdminDisplay;
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
