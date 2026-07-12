<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Profile;

use AssistantFoundation\Api\IAgentContextContributor;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentResourceFactory;

/**
 * Resolves context profiles into concrete configured context-contributor presets.
 */
final class AgentContextProfileResolver {

	public const SETTINGS_GROUP = 'agent-context-profile';
	public const PRESET_FIELD = 'contexts';

	public function __construct(
		private readonly ISettingsStore $settingsStore,
		private readonly IAgentComponentPresetRepository $presetRepository,
		private readonly IAgentResourceFactory $resourceFactory
	) {}

	/** @return array<int,array<string,mixed>> */
	public function getOptions(): array {
		$profiles = $this->loadProfilesIncludingLegacy();
		$rows = [];

		foreach ($profiles as $profile) {
			if (!$profile['enabled']) {
				continue;
			}
			$rows[] = [
				'id' => $profile['id'],
				'label' => $profile['label'],
				'description' => $profile['description'],
				'preset_count' => count($profile['presets']),
				'context_count' => count($profile['presets']),
				'legacy_derived' => (bool)($profile['legacy_derived'] ?? false)
			];
		}

		usort($rows, static function(array $left, array $right): int {
			$result = strcasecmp((string)$left['label'], (string)$right['label']);
			return $result !== 0 ? $result : strcasecmp((string)$left['id'], (string)$right['id']);
		});

		return $rows;
	}

	public function hasProfile(string $id): bool {
		try {
			return $this->getProfile($id)['presets'] !== [];
		}
		catch (\Throwable) {
			return false;
		}
	}

	/** @return array<string,mixed> */
	public function getProfile(string $id): array {
		$id = $this->normalizeId($id);
		if ($id === '') {
			throw new \InvalidArgumentException('Missing context profile id.');
		}

		$settings = $this->settingsStore->get(self::SETTINGS_GROUP, $id, []);
		if (is_array($settings) && $settings !== []) {
			return $this->normalizeProfile($id, $settings);
		}

		$legacy = $this->settingsStore->get(AgentMemoryProfileResolver::SETTINGS_GROUP, $id, []);
		if (is_array($legacy) && $legacy !== []) {
			$profile = $this->normalizeLegacyProfile($id, $legacy);
			if ($profile['presets'] !== []) {
				return $profile;
			}
		}

		throw new \RuntimeException('Context profile not found: ' . $id);
	}

	/** @return array<int,array<string,mixed>> */
	public function resolveComponents(string $profileId): array {
		$profileId = $this->normalizeId($profileId);
		if ($profileId === '') {
			return [];
		}

		$profile = $this->getProfile($profileId);
		if (!$profile['enabled']) {
			throw new \RuntimeException('Context profile is disabled: ' . $profileId);
		}

		$components = [];
		$order = 10;
		foreach ($profile['presets'] as $presetId) {
			$preset = $this->requirePreset($presetId);
			if (!$this->isContextPreset($preset)) {
				throw new \RuntimeException('Context profile preset is not a context contributor: ' . $presetId);
			}

			$components[] = [
				'preset' => $presetId,
				'attach_as' => ['context'],
				'enabled' => true,
				'order' => $order,
				'context_profile' => $profileId
			];
			$order += 10;
		}

		return $components;
	}

	/** @param array<string,mixed> $settings @return array<string,mixed> */
	public function normalizeProfile(string $id, array $settings): array {
		$id = $this->normalizeId((string)($settings['id'] ?? $id));
		$label = trim((string)($settings['label'] ?? ''));
		$presets = $settings[self::PRESET_FIELD] ?? ($settings['presets'] ?? []);

		return [
			'id' => $id,
			'label' => $label !== '' ? $label : $id,
			'description' => trim((string)($settings['description'] ?? '')),
			'enabled' => $this->toBool($settings['enabled'] ?? true),
			'presets' => $this->normalizeIds($presets),
			self::PRESET_FIELD => $this->normalizeIds($presets),
			'legacy_derived' => false
		];
	}

	/** @return array<int,array<string,mixed>> */
	public function getPresetOptions(): array {
		$rows = [];
		foreach ($this->presetRepository->getPresets() as $id => $preset) {
			if (!is_array($preset) || !$this->toBool($preset['enabled'] ?? true)) {
				continue;
			}
			if (!$this->isContextPreset($preset)) {
				continue;
			}

			$label = trim((string)($preset['label'] ?? ''));
			$meta = is_array($preset['meta'] ?? null) ? $preset['meta'] : [];
			$config = is_array($preset['config'] ?? null) ? $preset['config'] : [];
			$rows[] = [
				'id' => (string)$id,
				'label' => $label !== '' ? $label : (string)$id,
				'type' => trim((string)($preset['type'] ?? '')),
				'description' => trim((string)($meta['description'] ?? ($preset['description'] ?? ''))),
				'config' => $config,
				'config_summary' => $config === [] ? 'default resource configuration' : count($config) . ' configured value(s)'
			];
		}

		usort($rows, static function(array $left, array $right): int {
			$result = strcasecmp((string)$left['label'], (string)$right['label']);
			return $result !== 0 ? $result : strcasecmp((string)$left['id'], (string)$right['id']);
		});
		return $rows;
	}

	/** @return array<int,array<string,mixed>> */
	private function loadProfilesIncludingLegacy(): array {
		$result = [];
		$group = $this->settingsStore->getGroup(self::SETTINGS_GROUP);
		if (is_array($group)) {
			foreach ($group as $id => $settings) {
				if ((!is_string($id) && !is_int($id)) || !is_array($settings)) continue;
				$profile = $this->normalizeProfile((string)$id, $settings);
				$result[$profile['id']] = $profile;
			}
		}

		$legacyGroup = $this->settingsStore->getGroup(AgentMemoryProfileResolver::SETTINGS_GROUP);
		if (is_array($legacyGroup)) {
			foreach ($legacyGroup as $id => $settings) {
				if ((!is_string($id) && !is_int($id)) || !is_array($settings)) continue;
				$id = $this->normalizeId((string)$id);
				if ($id === '' || isset($result[$id])) continue;
				$profile = $this->normalizeLegacyProfile($id, $settings);
				if ($profile['presets'] !== []) {
					$result[$id] = $profile;
				}
			}
		}

		return array_values($result);
	}

	/** @param array<string,mixed> $settings @return array<string,mixed> */
	private function normalizeLegacyProfile(string $id, array $settings): array {
		$label = trim((string)($settings['label'] ?? ''));
		$presets = [];
		$entries = is_array($settings['entries'] ?? null) ? $settings['entries'] : [];

		foreach ($entries as $entry) {
			if (!is_array($entry) || !$this->toBool($entry['enabled'] ?? true)) continue;
			$presetId = $this->normalizeId((string)($entry['preset'] ?? ''));
			if ($presetId === '') continue;
			$role = strtolower(trim(str_replace('_', '-', (string)($entry['role'] ?? 'auto'))));
			if (in_array($role, ['memory', 'conversation', 'conversation-memory'], true)) continue;
			$preset = $this->presetRepository->getPreset($presetId, []);
			if ($preset !== [] && $this->isContextPreset($preset)) {
				$presets[] = $presetId;
			}
		}

		return [
			'id' => $id,
			'label' => ($label !== '' ? $label : $id) . ' [legacy derived]',
			'description' => trim((string)($settings['description'] ?? '')),
			'enabled' => $this->toBool($settings['enabled'] ?? true),
			'presets' => $this->normalizeIds($presets),
			self::PRESET_FIELD => $this->normalizeIds($presets),
			'legacy_derived' => true
		];
	}

	/** @param array<string,mixed> $preset */
	private function isContextPreset(array $preset): bool {
		$type = trim((string)($preset['type'] ?? ''));
		if ($type === '') return false;
		$resource = $this->resourceFactory->createResource($type);
		return $resource instanceof IAgentResource && $resource instanceof IAgentContextContributor;
	}

	/** @return array<string,mixed> */
	private function requirePreset(string $presetId): array {
		$preset = $this->presetRepository->getPreset($presetId, []);
		if ($preset === []) throw new \RuntimeException('Context profile references an unknown component preset: ' . $presetId);
		if (!$this->toBool($preset['enabled'] ?? true)) throw new \RuntimeException('Context profile references a disabled component preset: ' . $presetId);
		return $preset;
	}

	/** @return array<int,string> */
	private function normalizeIds(mixed $value): array {
		if (is_string($value)) $value = explode(',', $value);
		if (!is_array($value)) return [];
		$result = [];
		foreach ($value as $id) {
			$id = $this->normalizeId((string)$id);
			if ($id !== '') $result[$id] = $id;
		}
		return array_values($result);
	}

	private function normalizeId(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}

	private function toBool(mixed $value): bool {
		if (is_bool($value)) return $value;
		if (is_int($value) || is_float($value)) return $value !== 0;
		return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
	}
}
