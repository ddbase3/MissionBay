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

namespace Base3\MissionBay\Test\Reporting\Settings;

use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Reporting\Settings\AiUsageReportingSettings as Groups;
use Base3\MissionBay\Reporting\Settings\AiUsageReportingSettingsSeeder;
use PHPUnit\Framework\TestCase;

final class AiUsageReportingSettingsSeederTest extends TestCase {

	public function testFreshInstallationSeedsAllShippedReportingDefinitions(): void {
		$records = [];
		$calls = [];
		$seeder = new AiUsageReportingSettingsSeeder($this->settings($records, $calls), dirname(__DIR__, 3));
		$seeder->seed();
		$this->assertSame(['version' => 2], $records[Groups::GROUP_META][Groups::META_DEFAULTS]);
		$this->assertArrayHasKey('base3_missionbay_ai_usage', $records[Groups::GROUP_DATAHAWK_SOURCE]);
		foreach (['missionbay_ai_usage_tokens_timeline', 'missionbay_ai_usage_models', 'missionbay_ai_usage_operations', 'missionbay_ai_usage_metrics', 'missionbay_ai_usage_requests'] as $name) {
			$this->assertSame(true, $records[Groups::GROUP_VIZION][$name]['enabled']);
			$this->assertNotEmpty($records[Groups::GROUP_VIZION][$name]['definition']);
		}
		$this->assertSame(true, $records[Groups::GROUP_DATAHAWK_SOURCE]['base3_missionbay_ai_usage']['enabled']);
		$this->assertSame(['remove', Groups::GROUP_VIZION, 'missionbay_ai_usage_users'], $calls[count($calls) - 3]);
		$this->assertSame(['save'], $calls[count($calls) - 1]);
	}

	public function testExistingDefinitionsAreNotOverwrittenOnInitialInstall(): void {
		$records = [Groups::GROUP_VIZION => [
			'missionbay_ai_usage_models' => ['enabled' => false, 'definition' => ['custom' => true]]
		]];
		$calls = [];
		(new AiUsageReportingSettingsSeeder($this->settings($records, $calls), dirname(__DIR__, 3)))->seed();
		$this->assertSame(['enabled' => false, 'definition' => ['custom' => true]], $records[Groups::GROUP_VIZION]['missionbay_ai_usage_models']);
		$this->assertSame(['version' => 2], $records[Groups::GROUP_META][Groups::META_DEFAULTS]);
	}

	public function testUpgradeFromVersionOneReplacesExistingDefinitions(): void {
		$records = [
			Groups::GROUP_META => [Groups::META_DEFAULTS => ['version' => 1]],
			Groups::GROUP_VIZION => ['missionbay_ai_usage_models' => ['enabled' => false, 'definition' => ['obsolete' => true]]]
		];
		$calls = [];
		(new AiUsageReportingSettingsSeeder($this->settings($records, $calls), dirname(__DIR__, 3)))->seed();
		$this->assertSame(true, $records[Groups::GROUP_VIZION]['missionbay_ai_usage_models']['enabled']);
		$this->assertArrayNotHasKey('obsolete', $records[Groups::GROUP_VIZION]['missionbay_ai_usage_models']['definition']);
		$this->assertSame(['version' => 2], $records[Groups::GROUP_META][Groups::META_DEFAULTS]);
	}

	public function testCurrentAndLaterVersionsDoNotChangeSettings(): void {
		foreach ([2, 3] as $version) {
			$records = [Groups::GROUP_META => [Groups::META_DEFAULTS => ['version' => $version]]];
			$calls = [];
			(new AiUsageReportingSettingsSeeder($this->settings($records, $calls), dirname(__DIR__, 3)))->seed();
			$this->assertSame([], $calls);
			$this->assertSame(['version' => $version], $records[Groups::GROUP_META][Groups::META_DEFAULTS]);
		}
	}

	public function testAbsentDefinitionDirectoriesStillRecordVersionAndRemoveObsoleteDataset(): void {
		$records = [Groups::GROUP_VIZION => [
			'missionbay_ai_usage_users' => ['legacy' => true]
		]];
		$calls = [];
		(new AiUsageReportingSettingsSeeder($this->settings($records, $calls), sys_get_temp_dir() . '/missionbay-missing-path-' . bin2hex(random_bytes(10))))->seed();
		$this->assertArrayNotHasKey('missionbay_ai_usage_users', $records[Groups::GROUP_VIZION]);
		$this->assertSame(['version' => 2], $records[Groups::GROUP_META][Groups::META_DEFAULTS]);
		$this->assertSame([['remove', Groups::GROUP_VIZION, 'missionbay_ai_usage_users'], ['set', Groups::GROUP_META, Groups::META_DEFAULTS], ['save']], $calls);
	}

	public function testInvalidJsonRefusesToRecordSuccessfulVersion(): void {
		$root = sys_get_temp_dir() . '/missionbay-report-' . bin2hex(random_bytes(9));
		$directory = $root . '/resources/defaults/DataHawk/source';
		mkdir($directory, 0700, true);
		$file = $directory . '/invalid.json';
		file_put_contents($file, '{broken');
		$records = [];
		$calls = [];
		try {
			try {
				(new AiUsageReportingSettingsSeeder($this->settings($records, $calls), $root))->seed();
				$this->fail('Invalid JSON was accepted.');
			} catch (\RuntimeException $exception) {
				$this->assertStringContainsString('Invalid MissionBay reporting default JSON', $exception->getMessage());
			}
			$this->assertArrayNotHasKey(Groups::GROUP_META, $records);
			$this->assertSame([], $calls);
		} finally {
			unlink($file);
			rmdir($directory);
			rmdir(dirname($directory));
			rmdir(dirname($directory, 2));
			rmdir(dirname($directory, 3));
			rmdir($root);
		}
	}

	/** @param array<string,array<string,array<string,mixed>>> $records @param array<int,array<int,string>> $calls */
	private function settings(array &$records, array &$calls): ISettingsStore {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('get')->willReturnCallback(static fn(string $group, string $name, array $default = []): array => $records[$group][$name] ?? $default);
		$settings->method('has')->willReturnCallback(static fn(string $group, string $name): bool => isset($records[$group][$name]));
		$settings->method('set')->willReturnCallback(static function(string $group, string $name, array $value) use (&$records, &$calls): void {
			$calls[] = ['set', $group, $name];
			$records[$group][$name] = $value;
		});
		$settings->method('remove')->willReturnCallback(static function(string $group, string $name) use (&$records, &$calls): void {
			$calls[] = ['remove', $group, $name];
			unset($records[$group][$name]);
		});
		$settings->method('save')->willReturnCallback(static function() use (&$calls): void { $calls[] = ['save']; });
		return $settings;
	}
}
