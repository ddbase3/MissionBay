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

use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentComponentPresetRepository;

/**
 * AgentComponentPresetRepository
 *
 * Stores reusable component presets in the BASE3 settings store.
 */
class AgentComponentPresetRepository implements IAgentComponentPresetRepository {

	private const GROUP = 'agent-component-preset';

	public function __construct(private readonly ISettingsStore $settingsStore) {}

	public static function getName(): string {
		return 'agentcomponentpresetrepository';
	}

	public function getPresets(): array {
		$presets = $this->settingsStore->getGroup(self::GROUP);
		$result = [];

		foreach ($presets as $id => $preset) {
			if (!is_string($id) || !is_array($preset)) {
				continue;
			}

			$preset['id'] = (string)($preset['id'] ?? $id);
			$result[$id] = $preset;
		}

		ksort($result);

		return $result;
	}

	public function getPreset(string $id, array $default = []): array {
		$preset = $this->settingsStore->get(self::GROUP, $id, $default);

		if ($preset === $default) {
			return $default;
		}

		$preset['id'] = (string)($preset['id'] ?? $id);

		return $preset;
	}

	public function hasPreset(string $id): bool {
		return $this->settingsStore->has(self::GROUP, $id);
	}

	public function savePreset(string $id, array $preset): void {
		$preset['id'] = (string)($preset['id'] ?? $id);

		$this->settingsStore->set(self::GROUP, $id, $preset);
		$this->settingsStore->save();
	}

	public function removePreset(string $id): void {
		$this->settingsStore->remove(self::GROUP, $id);
		$this->settingsStore->save();
	}
}
