<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 * Licensed under GPL-3.0
 **********************************************************************/

namespace MissionBay\Service;

use AssistantFoundation\Api\IAgentConfigurationService;
use AssistantFoundation\Api\IAgentDefinition;
use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use InvalidArgumentException;
use LogicException;

/**
 * The shared configuration owner for both stored and class-defined agents.
 * Fixed identities are declared in plugin source; editable settings remain
 * in the existing SettingsStore group without any bootstrap seeding.
 */
final class AgentConfigurationService implements IAgentConfigurationService {

	private const SETTINGS_GROUP = 'agent';

	public function __construct(
		private readonly ISettingsStore $settingsStore,
		private readonly IClassMap $classMap
	) {}

	public function getAgents(string $settingsGroup = self::SETTINGS_GROUP): array {
		$agents = $this->settingsStore->getGroup($settingsGroup);
		if ($settingsGroup !== self::SETTINGS_GROUP) {
			return $agents;
		}

		foreach ($this->getDefinitions() as $id => $definition) {
			if (array_key_exists($id, $agents) && !is_array($agents[$id])) {
				throw new \UnexpectedValueException('Stored fixed agent configuration is invalid: ' . $id);
			}
			$stored = $agents[$id] ?? [];
			$agents[$id] = $this->effectiveSettings($definition, $stored);
		}

		return $agents;
	}

	public function getAgent(string $id, string $settingsGroup = self::SETTINGS_GROUP): ?array {
		if ($settingsGroup === self::SETTINGS_GROUP) {
			$definition = $this->getDefinitions()[$id] ?? null;
			if ($definition !== null) {
				$stored = $this->settingsStore->get($settingsGroup, $id, []);
				return $this->effectiveSettings($definition, $stored);
			}
		}

		$stored = $this->settingsStore->get($settingsGroup, $id, []);
		return $stored !== [] || $this->settingsStore->has($settingsGroup, $id)
			? $stored
			: null;
	}

	public function hasAgent(string $id, string $settingsGroup = self::SETTINGS_GROUP): bool {
		return ($settingsGroup === self::SETTINGS_GROUP && $this->isFixed($id))
			|| $this->settingsStore->has($settingsGroup, $id);
	}

	public function isFixed(string $id): bool {
		return isset($this->getDefinitions()[$id]);
	}

	public function saveAgent(string $id, array $settings): void {
		$this->assertValidId($id);
		$definition = $this->getDefinitions()[$id] ?? null;
		if ($definition !== null) {
			if (($settings['label'] ?? null) !== $definition->getLabel()) {
				throw new InvalidArgumentException('The identity label of a fixed agent cannot be changed: ' . $id);
			}
			$settings['label'] = $definition->getLabel();
		}

		$this->settingsStore->set(self::SETTINGS_GROUP, $id, $settings);
		$this->settingsStore->save();
	}

	public function deleteAgent(string $id): void {
		if ($this->isFixed($id)) {
			throw new LogicException('A fixed agent cannot be deleted: ' . $id);
		}
		$this->settingsStore->remove(self::SETTINGS_GROUP, $id);
		$this->settingsStore->save();
	}

	public function reload(): void {
		$this->settingsStore->reload();
	}

	/** @return array<string,IAgentDefinition> */
	private function getDefinitions(): array {
		$definitions = [];
		foreach ($this->classMap->getInstancesByInterface(IAgentDefinition::class) as $definition) {
			if (!$definition instanceof IAgentDefinition) {
				throw new LogicException('Discovered agent definition does not implement IAgentDefinition.');
			}
			$id = $definition->getId();
			$this->assertValidId($id);
			if (trim($definition->getLabel()) === '') {
				throw new LogicException('Fixed agent label must not be empty: ' . $id);
			}
			if (isset($definitions[$id])) {
				throw new LogicException('Duplicate fixed agent id: ' . $id);
			}
			$definitions[$id] = $definition;
		}
		return $definitions;
	}

	/** @param array<string,mixed> $stored @return array<string,mixed> */
	private function effectiveSettings(IAgentDefinition $definition, array $stored): array {
		return array_replace($definition->getDefaultSettings(), $stored, [
			'label' => $definition->getLabel()
		]);
	}

	private function assertValidId(string $id): void {
		if ($id === '' || preg_match('/^[a-z0-9._-]+$/D', $id) !== 1) {
			throw new InvalidArgumentException('Invalid fixed agent id: ' . $id);
		}
	}
}
