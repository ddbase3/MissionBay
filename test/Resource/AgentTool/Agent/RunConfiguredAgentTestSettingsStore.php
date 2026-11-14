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

namespace Base3\MissionBay\Test\Resource\AgentTool\Agent;

use Base3\CoreFoundation\Settings\Api\ISettingsStore;

final class RunConfiguredAgentTestSettingsStore implements ISettingsStore {

	/** @var array<string,array<string,array<string,mixed>>> */
	public array $datasets = [];
	/** @var array<int,array{string,string}> */
	public array $reads = [];
	public bool $throwOnGet = false;

	public function get(string $group, string $name, array $default = []): array {
		$this->reads[] = [$group, $name];
		if ($this->throwOnGet) {
			throw new \RuntimeException('Settings unavailable');
		}
		return $this->datasets[$group][$name] ?? $default;
	}

	public function set(string $group, string $name, array $settings): void {
		$this->datasets[$group][$name] = $settings;
	}

	public function has(string $group, string $name): bool {
		return isset($this->datasets[$group][$name]);
	}

	public function remove(string $group, string $name): void {
		unset($this->datasets[$group][$name]);
	}

	public function getGroup(string $group): array {
		return $this->datasets[$group] ?? [];
	}

	public function save(): void {}

	public function reload(): void {}
}
