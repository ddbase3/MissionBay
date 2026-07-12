<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Orchestrator\Profile;

use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use Base3\Settings\Api\ISettingsStore;

/**
 * Stores custom profiles and exposes non-persisted safe built-in profiles.
 */
final class AgentOrchestratorProfileRepository {

	public const SETTINGS_GROUP = 'agent-orchestrator-profile';
	public const DEFAULT_PROFILE_ID = 'standard';

	public function __construct(private readonly ISettingsStore $settingsStore) {}

	/** @return array<string,AgentOrchestratorProfile> */
	public function getProfiles(): array {
		$profiles = $this->getBuiltinProfiles();
		$stored = $this->settingsStore->getGroup(self::SETTINGS_GROUP);

		if (is_array($stored)) {
			foreach ($stored as $id => $settings) {
				if (!is_string($id) || !is_array($settings) || isset($profiles[$id])) {
					continue;
				}
				$profiles[$id] = $this->fromArray($id, $settings, false);
			}
		}

		uasort($profiles, static function(AgentOrchestratorProfile $a, AgentOrchestratorProfile $b): int {
			if ($a->isBuiltin() !== $b->isBuiltin()) {
				return $a->isBuiltin() ? -1 : 1;
			}
			$cmp = strcasecmp($a->getLabel(), $b->getLabel());
			return $cmp !== 0 ? $cmp : strcmp($a->getId(), $b->getId());
		});

		return $profiles;
	}

	public function getProfile(string $id): AgentOrchestratorProfile {
		$id = $this->normalizeId($id);
		if ($id === '') {
			$id = self::DEFAULT_PROFILE_ID;
		}

		$profiles = $this->getProfiles();
		if (!isset($profiles[$id])) {
			throw new \RuntimeException('Orchestrator profile not found: ' . $id);
		}
		if (!$profiles[$id]->isEnabled()) {
			throw new \RuntimeException('Orchestrator profile is disabled: ' . $id);
		}

		return $profiles[$id];
	}

	public function exists(string $id): bool {
		$id = $this->normalizeId($id);
		return $id !== '' && isset($this->getProfiles()[$id]);
	}

	public function isBuiltin(string $id): bool {
		$id = $this->normalizeId($id);
		return isset($this->getBuiltinProfiles()[$id]);
	}

	/** @param array<string,mixed> $settings */
	public function save(string $id, array $settings): AgentOrchestratorProfile {
		$id = $this->normalizeId($id);
		if ($id === '') {
			throw new \InvalidArgumentException('Orchestrator profile id must not be empty.');
		}
		if ($this->isBuiltin($id)) {
			throw new \RuntimeException('Built-in orchestrator profiles cannot be overwritten: ' . $id);
		}

		$profile = $this->fromArray($id, $settings, false);
		$this->settingsStore->set(self::SETTINGS_GROUP, $id, $profile->toArray());
		$this->settingsStore->save();

		return $profile;
	}

	public function remove(string $id): void {
		$id = $this->normalizeId($id);
		if ($this->isBuiltin($id)) {
			throw new \RuntimeException('Built-in orchestrator profiles cannot be deleted: ' . $id);
		}
		$this->settingsStore->remove(self::SETTINGS_GROUP, $id);
		$this->settingsStore->save();
	}

	public function reload(): void {
		$this->settingsStore->reload();
	}

	/** @return array<int,array<string,mixed>> */
	public function getOptions(): array {
		$options = [];
		foreach ($this->getProfiles() as $profile) {
			$options[] = [
				'id' => $profile->getId(),
				'label' => $profile->getLabel(),
				'description' => $profile->getDescription(),
				'mode' => $profile->getMode(),
				'enabled' => $profile->isEnabled(),
				'builtin' => $profile->isBuiltin(),
				'stage_ids' => $profile->getStageIds()
			];
		}
		return $options;
	}

	/** @return array<string,AgentOrchestratorProfile> */
	private function getBuiltinProfiles(): array {
		return [
			'simple' => $this->fromArray('simple', [
				'label' => 'Simple tool agent',
				'description' => 'One tool loop, bounded tool preselection, no compaction or semantic verification.',
				'enabled' => true,
				'mode' => AgentOrchestratorProfile::MODE_SIMPLE,
				'max_tool_loops' => 1,
				'optional_stages' => [
					'capability-discovery' => false,
					'capability-selection' => true,
					'context-compaction' => false,
					'semantic-verification' => false
				],
				'capability_selection' => [
					'enabled' => true,
					'strategy' => 'hybrid',
					'max_tools' => 12,
					'select_all_threshold' => 12,
					'sticky' => false
				]
			], true),
			'standard' => $this->fromArray('standard', [
				'label' => 'MissionBay standard',
				'description' => 'General-purpose multi-step orchestration with discovery, selection, compaction and verification.',
				'enabled' => true,
				'mode' => AgentOrchestratorProfile::MODE_STANDARD,
				'max_tool_loops' => 10,
				'optional_stages' => [
					'capability-discovery' => true,
					'capability-selection' => true,
					'context-compaction' => true,
					'semantic-verification' => true
				],
				'capability_selection' => [
					'enabled' => true,
					'strategy' => 'hybrid',
					'max_tools' => 16,
					'select_all_threshold' => 16,
					'sticky' => true
				]
			], true),
			'deliberate' => $this->fromArray('deliberate', [
				'label' => 'Deliberate evidence agent',
				'description' => 'Uses visible history first, creates a concise execution plan without an extra model call, limits repeated tool work and keeps semantic verification enabled.',
				'enabled' => true,
				'mode' => AgentOrchestratorProfile::MODE_DELIBERATE,
				'deliberate_planning' => true,
				'max_tool_loops' => 4,
				'optional_stages' => [
					'capability-discovery' => true,
					'capability-selection' => true,
					'context-compaction' => true,
					'semantic-verification' => true
				],
				'capability_selection' => [
					'enabled' => true,
					'strategy' => 'hybrid',
					'max_tools' => 12,
					'select_all_threshold' => 12,
					'sticky' => true
				]
			], true),
			'governed' => $this->fromArray('governed', [
				'label' => 'Governed mutation agent',
				'description' => 'Full pipeline for agents that may execute approved mutations. Approval, replay protection and commit guards remain mandatory services.',
				'enabled' => true,
				'mode' => AgentOrchestratorProfile::MODE_GOVERNED,
				'max_tool_loops' => 10,
				'optional_stages' => [
					'capability-discovery' => true,
					'capability-selection' => true,
					'context-compaction' => true,
					'semantic-verification' => true
				],
				'capability_selection' => [
					'enabled' => true,
					'strategy' => 'hybrid',
					'max_tools' => 16,
					'select_all_threshold' => 16,
					'sticky' => true
				]
			], true)
		];
	}

	/** @param array<string,mixed> $settings */
	private function fromArray(string $id, array $settings, bool $builtin): AgentOrchestratorProfile {
		$mode = strtolower(trim((string)($settings['mode'] ?? AgentOrchestratorProfile::MODE_STANDARD)));
		$defaults = $this->defaultsForMode($mode);
		$optional = is_array($settings['optional_stages'] ?? null) ? $settings['optional_stages'] : [];
		$selection = is_array($settings['capability_selection'] ?? null) ? $settings['capability_selection'] : [];

		$label = trim((string)($settings['label'] ?? ''));
		if ($label === '') {
			$label = $id;
		}

		return new AgentOrchestratorProfile(
			id: $id,
			label: $label,
			description: trim((string)($settings['description'] ?? '')),
			enabled: $this->toBool($settings['enabled'] ?? true),
			mode: $mode,
			maxToolLoops: max(1, min(100, (int)($settings['max_tool_loops'] ?? $defaults['max_tool_loops']))),
			capabilityDiscoveryEnabled: $this->toBool($optional['capability-discovery'] ?? $defaults['optional_stages']['capability-discovery']),
			capabilitySelectionEnabled: $this->toBool($optional['capability-selection'] ?? $defaults['optional_stages']['capability-selection']),
			contextCompactionEnabled: $this->toBool($optional['context-compaction'] ?? $defaults['optional_stages']['context-compaction']),
			semanticVerificationEnabled: $this->toBool($optional['semantic-verification'] ?? $defaults['optional_stages']['semantic-verification']),
			capabilitySelection: AgentCapabilitySelectionConfig::fromArray(array_merge($defaults['capability_selection'], $selection)),
			deliberatePlanningEnabled: $this->toBool($settings['deliberate_planning'] ?? $defaults['deliberate_planning']),
			builtin: $builtin
		);
	}

	/** @return array<string,mixed> */
	private function defaultsForMode(string $mode): array {
		return match ($mode) {
			AgentOrchestratorProfile::MODE_SIMPLE => [
				'deliberate_planning' => false,
				'max_tool_loops' => 1,
				'optional_stages' => [
					'capability-discovery' => false,
					'capability-selection' => true,
					'context-compaction' => false,
					'semantic-verification' => false
				],
				'capability_selection' => ['enabled' => true, 'strategy' => 'hybrid', 'max_tools' => 12, 'select_all_threshold' => 12, 'sticky' => false]
			],
			AgentOrchestratorProfile::MODE_DELIBERATE => [
				'deliberate_planning' => true,
				'max_tool_loops' => 4,
				'optional_stages' => [
					'capability-discovery' => true,
					'capability-selection' => true,
					'context-compaction' => true,
					'semantic-verification' => true
				],
				'capability_selection' => ['enabled' => true, 'strategy' => 'hybrid', 'max_tools' => 12, 'select_all_threshold' => 12, 'sticky' => true]
			],
			AgentOrchestratorProfile::MODE_GOVERNED,
			AgentOrchestratorProfile::MODE_STANDARD => [
				'deliberate_planning' => false,
				'max_tool_loops' => 10,
				'optional_stages' => [
					'capability-discovery' => true,
					'capability-selection' => true,
					'context-compaction' => true,
					'semantic-verification' => true
				],
				'capability_selection' => ['enabled' => true, 'strategy' => 'hybrid', 'max_tools' => 16, 'select_all_threshold' => 16, 'sticky' => true]
			],
			default => throw new \InvalidArgumentException('Unknown orchestrator profile mode: ' . $mode)
		};
	}

	private function normalizeId(string $id): string {
		$id = strtolower(trim($id));
		return preg_replace('/[^a-z0-9._-]+/', '', $id) ?? '';
	}

	private function toBool(mixed $value): bool {
		if (is_bool($value)) return $value;
		if (is_int($value)) return $value !== 0;
		return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
	}
}
