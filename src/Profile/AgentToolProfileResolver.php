<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Profile;

use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentComponentPresetRepository;

/**
 * Resolves operator-facing tool profiles into AgentComponentFlowBuilder input.
 * A preset implementing both tool and memory is attached as both, preserving
 * the existing dual-capability UserPrefs-style pattern.
 */
final class AgentToolProfileResolver {

	public const SETTINGS_GROUP = 'tool-profile';

	public function __construct(
		private readonly ISettingsStore $settingsStore,
		private readonly IAgentComponentPresetRepository $presetRepository
	) {}

	/** @return array<int,array<string,mixed>> */
	public function getOptions(): array {
		$rows = [];
		$group = $this->settingsStore->getGroup(self::SETTINGS_GROUP);
		if (!is_array($group)) {
			return [];
		}

		foreach ($group as $id => $settings) {
			if (!is_string($id) || !is_array($settings)) {
				continue;
			}
			$profile = $this->normalizeProfile($id, $settings);
			if (!$profile['enabled'] || !$profile['internal_enabled']) {
				continue;
			}
			$rows[] = [
				'id' => $profile['id'],
				'label' => $profile['label'],
				'description' => $profile['description'],
				'tool_count' => count($profile['tools']),
				'mcp_enabled' => $profile['mcp_enabled']
			];
		}

		usort($rows, static function(array $a, array $b): int {
			$cmp = strcasecmp((string)$a['label'], (string)$b['label']);
			return $cmp !== 0 ? $cmp : strcmp((string)$a['id'], (string)$b['id']);
		});

		return $rows;
	}

	/**
	 * @param array<int,string> $profileIds
	 * @return array<int,array<string,mixed>>
	 */
	public function resolveComponents(array $profileIds): array {
		$resolved = [];
		$order = 10;

		foreach ($this->normalizeIds($profileIds) as $profileId) {
			$settings = $this->settingsStore->get(self::SETTINGS_GROUP, $profileId, []);
			if (!is_array($settings) || $settings === []) {
				throw new \RuntimeException('Tool profile not found: ' . $profileId);
			}
			$profile = $this->normalizeProfile($profileId, $settings);
			if (!$profile['enabled']) {
				throw new \RuntimeException('Tool profile is disabled: ' . $profileId);
			}
			if (!$profile['internal_enabled']) {
				throw new \RuntimeException('Tool profile is not enabled for internal agents: ' . $profileId);
			}

			foreach ($profile['tools'] as $presetId) {
				$preset = $this->presetRepository->getPreset($presetId, []);
				if ($preset === []) {
					throw new \RuntimeException('Tool profile references an unknown component preset: ' . $presetId);
				}
				if (!$this->toBool($preset['enabled'] ?? true)) {
					throw new \RuntimeException('Tool profile references a disabled component preset: ' . $presetId);
				}

				$capabilities = $this->normalizeCapabilities($preset['capabilities'] ?? []);
				if (!in_array('tool', $capabilities, true)) {
					throw new \RuntimeException('Tool profile preset does not expose the tool capability: ' . $presetId);
				}

				if (!isset($resolved[$presetId])) {
					$resolved[$presetId] = [
						'preset' => $presetId,
						'attach_as' => $capabilities,
						'enabled' => true,
						'order' => $order
					];
					$order += 10;
					continue;
				}

				$resolved[$presetId]['attach_as'] = array_values(array_unique(array_merge(
					(array)$resolved[$presetId]['attach_as'],
					$capabilities
				)));
			}
		}

		return array_values($resolved);
	}

	/** @param array<string,mixed> $settings @return array<string,mixed> */
	private function normalizeProfile(string $id, array $settings): array {
		$type = strtolower(trim((string)($settings['type'] ?? 'mcp')));
		$internalEnabled = array_key_exists('internal_enabled', $settings)
			? $this->toBool($settings['internal_enabled'])
			: true;
		$mcpEnabled = array_key_exists('mcp_enabled', $settings)
			? $this->toBool($settings['mcp_enabled'])
			: in_array($type, ['mcp', 'hybrid'], true);
		$label = trim((string)($settings['label'] ?? ''));

		return [
			'id' => $id,
			'label' => $label !== '' ? $label : $id,
			'description' => trim((string)($settings['description'] ?? '')),
			'enabled' => $this->toBool($settings['enabled'] ?? true),
			'internal_enabled' => $internalEnabled,
			'mcp_enabled' => $mcpEnabled,
			'tools' => $this->normalizeIds(is_array($settings['tools'] ?? null) ? $settings['tools'] : [])
		];
	}

	/** @return array<int,string> */
	private function normalizeCapabilities(mixed $value): array {
		if (is_string($value)) {
			$value = explode(',', $value);
		}
		if (!is_array($value)) {
			return ['tool'];
		}
		$result = [];
		foreach ($value as $capability) {
			$capability = strtolower(trim((string)$capability));
			if (in_array($capability, ['tool', 'memory'], true)) {
				$result[] = $capability;
			}
		}
		return $result === [] ? ['tool'] : array_values(array_unique($result));
	}

	/** @param array<int,mixed> $ids @return array<int,string> */
	private function normalizeIds(array $ids): array {
		$result = [];
		foreach ($ids as $id) {
			$id = strtolower(trim((string)$id));
			$id = preg_replace('/[^a-z0-9._-]+/', '', $id) ?? '';
			if ($id !== '') {
				$result[$id] = $id;
			}
		}
		return array_values($result);
	}

	private function toBool(mixed $value): bool {
		if (is_bool($value)) return $value;
		if (is_int($value)) return $value !== 0;
		return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
	}
}
