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
		return new AgentToolProfileResolverTestDouble1($groups);
	}

	private function presetRepository(): IAgentComponentPresetRepository {
		return new AgentToolProfileResolverTestDouble2();
	}
}

require_once __DIR__ . '/AgentToolProfileResolverTestDouble1.php';

require_once __DIR__ . '/AgentToolProfileResolverTestDouble2.php';
