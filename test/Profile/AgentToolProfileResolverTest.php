<?php declare(strict_types=1);

namespace MissionBay\Test\Profile;

use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Profile\AgentToolProfileResolver;
use PHPUnit\Framework\TestCase;

final class AgentToolProfileResolverTest extends TestCase {

	public function testSelectedToolPresetsRetainAllDeclaredCapabilities(): void {
		$resolver = new AgentToolProfileResolver(
			$this->settingsStore([
				AgentToolProfileResolver::SETTINGS_GROUP => [
					'main-tools' => [
						'label' => 'Main tools',
						'enabled' => true,
						'internal_enabled' => true,
						'tools' => ['tool-only', 'user-prefs', 'tool-memory', 'tool-context-memory']
					]
				]
			]),
			$this->presetRepository()
		);

		$this->assertSame([
			[
				'preset' => 'tool-only',
				'attach_as' => ['tool'],
				'enabled' => true,
				'order' => 10
			],
			[
				'preset' => 'user-prefs',
				'attach_as' => ['tool', 'context'],
				'enabled' => true,
				'order' => 20
			],
			[
				'preset' => 'tool-memory',
				'attach_as' => ['tool', 'memory'],
				'enabled' => true,
				'order' => 30
			],
			[
				'preset' => 'tool-context-memory',
				'attach_as' => ['tool', 'context', 'memory'],
				'enabled' => true,
				'order' => 40
			]
		], $resolver->resolveComponents(['main-tools']));
	}

	public function testRepeatedPresetAcrossToolProfilesIsResolvedOnceWithAllCapabilities(): void {
		$resolver = new AgentToolProfileResolver(
			$this->settingsStore([
				AgentToolProfileResolver::SETTINGS_GROUP => [
					'primary' => [
						'enabled' => true,
						'internal_enabled' => true,
						'tools' => ['user-prefs']
					],
					'secondary' => [
						'enabled' => true,
						'internal_enabled' => true,
						'tools' => ['user-prefs']
					]
				]
			]),
			$this->presetRepository()
		);

		$this->assertSame([[
			'preset' => 'user-prefs',
			'attach_as' => ['tool', 'context'],
			'enabled' => true,
			'order' => 10
		]], $resolver->resolveComponents(['primary', 'secondary']));
	}

	/** @param array<string,array<string,array<string,mixed>>> $groups */
	private function settingsStore(array $groups): ISettingsStore {
		return new class($groups) implements ISettingsStore {
			public function __construct(private array $groups) {}
			public function get(string $group, string $name, array $default = []): array { return $this->groups[$group][$name] ?? $default; }
			public function set(string $group, string $name, array $settings): void { $this->groups[$group][$name] = $settings; }
			public function has(string $group, string $name): bool { return isset($this->groups[$group][$name]); }
			public function remove(string $group, string $name): void { unset($this->groups[$group][$name]); }
			public function getGroup(string $group): array { return $this->groups[$group] ?? []; }
			public function save(): void {}
			public function reload(): void {}
		};
	}

	private function presetRepository(): IAgentComponentPresetRepository {
		return new class implements IAgentComponentPresetRepository {
			private array $presets = [
				'tool-only' => [
					'id' => 'tool-only',
					'enabled' => true,
					'capabilities' => ['tool']
				],
				'user-prefs' => [
					'id' => 'user-prefs',
					'enabled' => true,
					'capabilities' => ['tool', 'context']
				],
				'tool-memory' => [
					'id' => 'tool-memory',
					'enabled' => true,
					'capabilities' => ['tool', 'memory']
				],
				'tool-context-memory' => [
					'id' => 'tool-context-memory',
					'enabled' => true,
					'capabilities' => ['tool', 'context', 'memory']
				]
			];
			public function getPresets(): array { return $this->presets; }
			public function getPreset(string $id, array $default = []): array { return $this->presets[$id] ?? $default; }
			public function hasPreset(string $id): bool { return isset($this->presets[$id]); }
			public function savePreset(string $id, array $preset): void { $this->presets[$id] = $preset; }
			public function removePreset(string $id): void { unset($this->presets[$id]); }
		};
	}
}
