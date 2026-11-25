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

namespace Base3\MissionBay\Orchestrator\Profile;

use Base3\AssistantFoundation\Api\IAgentOrchestratorProfile;
use Base3\AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;

/**
 * Stores custom profiles and exposes non-persisted safe built-in profiles.
 */
final class AgentOrchestratorProfileRepository {

	public const SETTINGS_GROUP = 'agent-orchestrator-profile';
	public const DEFAULT_PROFILE_ID = 'native-tool-loop';

	public function __construct(
		private readonly ISettingsStore $settingsStore,
		private readonly IClassMap $classMap
	) {}

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
		$id = self::resolveProfileId($id);

		$profiles = $this->getProfiles();
		if (!isset($profiles[$id])) {
			throw new \RuntimeException('Orchestrator profile not found: ' . $id);
		}
		if (!$profiles[$id]->isEnabled()) {
			throw new \RuntimeException('Orchestrator profile is disabled: ' . $id);
		}

		return $profiles[$id];
	}

	/**
	 * Resolves the effective profile identifier, without loading or persisting a profile.
	 * Only an omitted or blank choice selects the default.
	 */
	public static function resolveProfileId(string $id): string {
		if (trim($id) === '') {
			return self::DEFAULT_PROFILE_ID;
		}
		$normalized = self::normalizeId($id);
		return $normalized !== '' ? $normalized : $id;
	}

	public function exists(string $id): bool {
		$id = self::normalizeId($id);
		return $id !== '' && isset($this->getProfiles()[$id]);
	}

	public function isBuiltin(string $id): bool {
		$id = self::normalizeId($id);
		return isset($this->getBuiltinProfiles()[$id]);
	}

	/** @param array<string,mixed> $settings */
	public function save(string $id, array $settings): AgentOrchestratorProfile {
		$id = self::normalizeId($id);
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
		$id = self::normalizeId($id);
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
				'max_tool_loops' => $profile->getMaxToolLoops(),
				'enabled' => $profile->isEnabled(),
				'builtin' => $profile->isBuiltin(),
				'stage_ids' => $profile->getStageIds(),
				'model_decision_strategy' => $profile->getModelDecision()->getStrategy()
			];
		}
		return $options;
	}

	/** @return array<string,AgentOrchestratorProfile> */
	private function getBuiltinProfiles(): array {
		$profiles = [];
		foreach ($this->classMap->getInstancesByInterface(IAgentOrchestratorProfile::class) as $definition) {
			if (!$definition instanceof IAgentOrchestratorProfile) {
				throw new \LogicException('Discovered definition does not implement IAgentOrchestratorProfile.');
			}
			if ($definition::getRuntimeId() !== 'missionbay') {
				continue;
			}
			$id = $definition->getId();
			if ($id === '' || preg_match('/^[a-z0-9._-]+$/D', $id) !== 1) {
				throw new \InvalidArgumentException('Invalid orchestrator profile id: ' . $id);
			}
			if (isset($profiles[$id])) {
				throw new \LogicException('Duplicate orchestrator profile id: ' . $id);
			}
			$profiles[$id] = $this->fromArray($id, $definition->getSettings(), true);
		}
		return $profiles;
	}

	/** @param array<string,mixed> $settings */
	private function fromArray(string $id, array $settings, bool $builtin): AgentOrchestratorProfile {
		$mode = strtolower(trim((string)($settings['mode'] ?? AgentOrchestratorProfile::MODE_STANDARD)));
		$defaults = $this->defaultsForMode($mode);
		$optional = is_array($settings['optional_stages'] ?? null) ? $settings['optional_stages'] : [];
		$selection = is_array($settings['capability_selection'] ?? null) ? $settings['capability_selection'] : [];
		$modelDecision = is_array($settings['model_decision'] ?? null) ? $settings['model_decision'] : [];
		$loopLimit = $settings['max_tool_loops'] ?? $defaults['max_tool_loops'];
		// Preserve the previous normalization for non-zero and malformed limits.
		$maxToolLoops = is_numeric($loopLimit) && (float)$loopLimit === 0.0
			? 0
			: max(1, min(100, (int)$loopLimit));
		if ($maxToolLoops === 0) {
			$optional = array_fill_keys(array_keys($defaults['optional_stages']), false);
			$selection['selection_unit'] = AgentCapabilitySelectionConfig::SELECTION_UNIT_FUNCTION;
		}
		$selectionStrategy = strtolower(trim((string)($selection['strategy'] ?? $defaults['capability_selection']['strategy'])));
		$hasExplicitAiSelectionStage = array_key_exists('ai-capability-selection', $optional);
		$legacySemanticSelection = !$hasExplicitAiSelectionStage
			&& $selectionStrategy === AgentCapabilitySelectionConfig::STRATEGY_SEMANTIC
			&& $this->toBool($optional['capability-selection'] ?? $defaults['optional_stages']['capability-selection']);
		$capabilitySelectionEnabled = $legacySemanticSelection
			? false
			: $this->toBool($optional['capability-selection'] ?? $defaults['optional_stages']['capability-selection']);
		$aiCapabilitySelectionEnabled = $legacySemanticSelection
			? true
			: $this->toBool($optional['ai-capability-selection'] ?? $defaults['optional_stages']['ai-capability-selection']);

		if ($selectionStrategy === AgentCapabilitySelectionConfig::STRATEGY_SEMANTIC || $aiCapabilitySelectionEnabled) {
			$selection['strategy'] = AgentCapabilitySelectionConfig::STRATEGY_HYBRID;
		}
		$selection['enabled'] = $capabilitySelectionEnabled || $aiCapabilitySelectionEnabled;

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
			maxToolLoops: $maxToolLoops,
			capabilityDiscoveryEnabled: $this->toBool($optional['capability-discovery'] ?? $defaults['optional_stages']['capability-discovery']),
			capabilitySelectionEnabled: $capabilitySelectionEnabled,
			aiCapabilitySelectionEnabled: $aiCapabilitySelectionEnabled,
			contextCompactionEnabled: $this->toBool($optional['context-compaction'] ?? $defaults['optional_stages']['context-compaction']),
			semanticVerificationEnabled: $this->toBool($optional['semantic-verification'] ?? $defaults['optional_stages']['semantic-verification']),
			capabilitySelection: AgentCapabilitySelectionConfig::fromArray(array_merge($defaults['capability_selection'], $selection)),
			modelDecision: AgentModelDecisionConfig::fromArray(array_merge($defaults['model_decision'], $modelDecision)),
			deliberatePlanningEnabled: $maxToolLoops > 0 && $this->toBool($settings['deliberate_planning'] ?? $defaults['deliberate_planning']),
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
					'ai-capability-selection' => false,
					'context-compaction' => false,
					'semantic-verification' => false
				],
				'model_decision' => AgentModelDecisionConfig::aiGuarded()->toArray(),
				'capability_selection' => ['enabled' => true, 'strategy' => 'hybrid', 'max_tools' => 12, 'select_all_threshold' => 12, 'sticky' => false]
			],
			AgentOrchestratorProfile::MODE_DELIBERATE => [
				'deliberate_planning' => true,
				'max_tool_loops' => 4,
				'optional_stages' => [
					'capability-discovery' => true,
					'capability-selection' => true,
					'ai-capability-selection' => false,
					'context-compaction' => true,
					'semantic-verification' => true
				],
				'model_decision' => AgentModelDecisionConfig::aiGuarded()->toArray(),
				'capability_selection' => ['enabled' => true, 'strategy' => 'hybrid', 'max_tools' => 12, 'select_all_threshold' => 12, 'sticky' => true]
			],
			AgentOrchestratorProfile::MODE_GOVERNED,
			AgentOrchestratorProfile::MODE_STANDARD => [
				'deliberate_planning' => false,
				'max_tool_loops' => 10,
				'optional_stages' => [
					'capability-discovery' => true,
					'capability-selection' => true,
					'ai-capability-selection' => false,
					'context-compaction' => true,
					'semantic-verification' => true
				],
				'model_decision' => AgentModelDecisionConfig::aiGuarded()->toArray(),
				'capability_selection' => ['enabled' => true, 'strategy' => 'hybrid', 'max_tools' => 16, 'select_all_threshold' => 16, 'sticky' => true]
			],
			default => throw new \InvalidArgumentException('Unknown orchestrator profile mode: ' . $mode)
		};
	}

	private static function normalizeId(string $id): string {
		$id = strtolower(trim($id));
		return preg_replace('/[^a-z0-9._-]+/', '', $id) ?? '';
	}

	private function toBool(mixed $value): bool {
		if (is_bool($value)) return $value;
		if (is_int($value)) return $value !== 0;
		return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
	}
}
