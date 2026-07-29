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

namespace MissionBay\Profile;

use AssistantFoundation\Api\IAgentConversationMemory;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentResourceFactory;

/**
 * Resolves conversation-memory profiles into concrete configured presets.
 */
final class AgentMemoryProfileResolver {

	public const SETTINGS_GROUP = 'agent-memory-profile';
	public const PRESET_FIELD = 'memories';

	public function __construct(
		private readonly ISettingsStore $settingsStore,
		private readonly IAgentComponentPresetRepository $presetRepository,
		private readonly IAgentResourceFactory $resourceFactory
	) {}

	/** @return array<int,array<string,mixed>> */
	public function getOptions(): array {
		$rows = [];
		$group = $this->settingsStore->getGroup(self::SETTINGS_GROUP);

		if (!is_array($group)) {
			return [];
		}

		foreach ($group as $id => $settings) {
			if ((!is_string($id) && !is_int($id)) || !is_array($settings)) {
				continue;
			}

			$profile = $this->normalizeProfile((string)$id, $settings);
			if (!$profile['enabled']) {
				continue;
			}

			$rows[] = [
				'id' => $profile['id'],
				'label' => $profile['label'],
				'description' => $profile['description'],
				'preset_count' => count($profile[self::PRESET_FIELD]),
				'memory_count' => count($profile[self::PRESET_FIELD])
			];
		}

		usort($rows, static function(array $left, array $right): int {
			$result = strcasecmp((string)$left['label'], (string)$right['label']);
			return $result !== 0 ? $result : strcasecmp((string)$left['id'], (string)$right['id']);
		});

		return $rows;
	}

	/** @return array<string,mixed> */
	public function getProfile(string $id): array {
		$id = $this->normalizeId($id);
		if ($id === '') {
			throw new \InvalidArgumentException('Missing memory profile id.');
		}

		$settings = $this->settingsStore->get(self::SETTINGS_GROUP, $id, []);
		if (!is_array($settings) || $settings === []) {
			throw new \RuntimeException('Memory profile not found: ' . $id);
		}

		return $this->normalizeProfile($id, $settings);
	}

	/** @return array<int,array<string,mixed>> */
	public function resolveComponents(string $profileId): array {
		$profileId = $this->normalizeId($profileId);
		if ($profileId === '') {
			return [];
		}

		$profile = $this->getProfile($profileId);
		if (!$profile['enabled']) {
			throw new \RuntimeException('Memory profile is disabled: ' . $profileId);
		}

		if (count($profile[self::PRESET_FIELD]) !== 1) {
			throw new \RuntimeException('Memory profile must contain exactly one conversation memory: ' . $profileId);
		}

		$components = [];
		$order = 10;

		foreach ($profile[self::PRESET_FIELD] as $presetId) {
			$preset = $this->requirePreset($presetId);
			if (!$this->isConversationMemoryPreset($preset)) {
				throw new \RuntimeException('Memory profile preset is not a conversation memory: ' . $presetId);
			}

			$components[] = [
				'preset' => $presetId,
				'attach_as' => ['memory'],
				'enabled' => true,
				'order' => $order,
				'memory_profile' => $profileId,
				'memory_config' => [
					'enabled' => true,
					'read_enabled' => true,
					'write_enabled' => true
				]
			];
			$order += 10;
		}

		return $components;
	}

	/** @param array<string,mixed> $settings @return array<string,mixed> */
	public function normalizeProfile(string $id, array $settings): array {
		$id = $this->normalizeId((string)($settings['id'] ?? $id));
		$label = trim((string)($settings['label'] ?? ''));
		$presets = $settings[self::PRESET_FIELD] ?? [];

		return [
			'id' => $id,
			'label' => $label !== '' ? $label : $id,
			'description' => trim((string)($settings['description'] ?? '')),
			'enabled' => $this->toBool($settings['enabled'] ?? true),
			self::PRESET_FIELD => $this->normalizeIds($presets)
		];
	}

	/** @return array<int,array<string,mixed>> */
	public function getPresetOptions(): array {
		$rows = [];

		foreach ($this->presetRepository->getPresets() as $id => $preset) {
			if (!is_array($preset) || !$this->toBool($preset['enabled'] ?? true)) {
				continue;
			}
			if (!$this->isConversationMemoryPreset($preset)) {
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
				'config_summary' => $this->summarizeConfig($config)
			];
		}

		usort($rows, static function(array $left, array $right): int {
			$result = strcasecmp((string)$left['label'], (string)$right['label']);
			return $result !== 0 ? $result : strcasecmp((string)$left['id'], (string)$right['id']);
		});

		return $rows;
	}

	/** @param array<string,mixed> $preset */
	private function isConversationMemoryPreset(array $preset): bool {
		$type = trim((string)($preset['type'] ?? ''));
		if ($type === '') {
			return false;
		}

		$resource = $this->resourceFactory->createResource($type);
		if (!$resource instanceof IAgentResource) {
			return false;
		}

		return $resource instanceof IAgentConversationMemory;
	}

	/** @return array<string,mixed> */
	private function requirePreset(string $presetId): array {
		$preset = $this->presetRepository->getPreset($presetId, []);
		if ($preset === []) {
			throw new \RuntimeException('Memory profile references an unknown component preset: ' . $presetId);
		}
		if (!$this->toBool($preset['enabled'] ?? true)) {
			throw new \RuntimeException('Memory profile references a disabled component preset: ' . $presetId);
		}
		return $preset;
	}

	/** @param array<string,mixed> $config */
	private function summarizeConfig(array $config): string {
		$parts = [];
		foreach (['namespace', 'max', 'priority'] as $key) {
			if (!array_key_exists($key, $config)) {
				continue;
			}
			$value = $this->unwrapValue($config[$key]);
			if (is_scalar($value) || $value === null) {
				$parts[] = $key . '=' . ($value === null ? 'null' : (string)$value);
			}
		}
		return $parts !== [] ? implode(', ', $parts) : ($config === [] ? 'default resource configuration' : count($config) . ' configured value(s)');
	}

	private function unwrapValue(mixed $value): mixed {
		if (is_array($value) && array_key_exists('mode', $value) && array_key_exists('value', $value)) {
			return $value['value'];
		}
		return $value;
	}

	/** @return array<int,string> */
	private function normalizeIds(mixed $value): array {
		if (is_string($value)) {
			$value = explode(',', $value);
		}
		if (!is_array($value)) {
			return [];
		}

		$result = [];
		foreach ($value as $id) {
			$id = $this->normalizeId((string)$id);
			if ($id !== '') {
				$result[$id] = $id;
			}
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
