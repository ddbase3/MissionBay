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

namespace Base3\MissionBay\Test\Profile;

use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IAgentComponentPresetRepository;
use Base3\MissionBay\Profile\AgentToolProfileResolver;
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
