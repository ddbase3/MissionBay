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

namespace MissionBay\Reporting\Settings;

use Base3\Settings\Api\ISettingsStore;

final class AiUsageReportingSettingsSeeder {

	private const DEFAULTS_VERSION = 2;

	public function __construct(
		private readonly ISettingsStore $settingsStore,
		private readonly string $pluginRoot
	) {}

	public function seed(): void {
		$meta = $this->settingsStore->get(AiUsageReportingSettings::GROUP_META, AiUsageReportingSettings::META_DEFAULTS, []);
		$currentVersion = (int)($meta['version'] ?? 0);
		if($currentVersion >= self::DEFAULTS_VERSION) {
			return;
		}

		$replaceExisting = $currentVersion > 0;

		$this->seedDirectory(
			AiUsageReportingSettings::GROUP_DATAHAWK_SOURCE,
			$this->pluginRoot . '/resources/defaults/DataHawk/source',
			$replaceExisting
		);
		$this->seedDirectory(
			AiUsageReportingSettings::GROUP_VIZION,
			$this->pluginRoot . '/resources/defaults/Vizion',
			$replaceExisting
		);

		$this->settingsStore->remove(
			AiUsageReportingSettings::GROUP_VIZION,
			'missionbay_ai_usage_users'
		);

		$this->settingsStore->set(AiUsageReportingSettings::GROUP_META, AiUsageReportingSettings::META_DEFAULTS, [
			'version' => self::DEFAULTS_VERSION
		]);
		$this->settingsStore->save();
	}

	private function seedDirectory(string $group, string $directory, bool $replaceExisting): void {
		$files = glob(rtrim($directory, DIRECTORY_SEPARATOR . '/\\') . DIRECTORY_SEPARATOR . '*.json') ?: [];
		sort($files);

		foreach($files as $file) {
			$name = pathinfo($file, PATHINFO_FILENAME);
			if($name === '' || (!$replaceExisting && $this->settingsStore->has($group, $name))) {
				continue;
			}

			$this->settingsStore->set($group, $name, [
				'enabled' => true,
				'definition' => $this->loadDefinition($file)
			]);
		}
	}

	private function loadDefinition(string $file): array {
		$json = file_get_contents($file);
		if($json === false) {
			throw new \RuntimeException('Unable to read MissionBay reporting default: ' . $file);
		}

		$definition = json_decode($json, true);
		if(!is_array($definition) || json_last_error() !== JSON_ERROR_NONE) {
			throw new \RuntimeException('Invalid MissionBay reporting default JSON in ' . $file . ': ' . json_last_error_msg());
		}

		return $definition;
	}
}
