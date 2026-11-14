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

namespace Base3\MissionBay\Test\Reporting;

use Base3\CoreFoundation\Api\IContainer;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Reporting\Hook\AiUsageReportingSettingsSeedListener;
use Base3\MissionBay\Reporting\Settings\AiUsageReportingSettingsSeeder;
use PHPUnit\Framework\TestCase;

final class AiUsageReportingSeedHookBehaviorTest extends TestCase {

	public function testSubscribesOnlyToMigratedLifecycle(): void {
		$this->assertSame(['bootstrap.migrated' => 0], AiUsageReportingSettingsSeedListener::getSubscribedHooks());
	}

	public function testInactiveWithoutRegisteredSeeder(): void {
		$container = $this->createMock(IContainer::class);
		$container->expects($this->once())->method('has')->with(AiUsageReportingSettingsSeeder::class)->willReturn(false);
		$this->assertFalse((new AiUsageReportingSettingsSeedListener($container))->isActive());
	}

	public function testActiveWithRegisteredSeeder(): void {
		$container = $this->createMock(IContainer::class);
		$container->expects($this->once())->method('has')->with(AiUsageReportingSettingsSeeder::class)->willReturn(true);
		$this->assertTrue((new AiUsageReportingSettingsSeedListener($container))->isActive());
	}

	public function testUnrelatedHooksNeverResolveSeeder(): void {
		$container = $this->createMock(IContainer::class);
		$container->expects($this->never())->method('get');
		$this->assertNull((new AiUsageReportingSettingsSeedListener($container))->handle('bootstrap.start'));
	}

	public function testMissingOrIncorrectSeederIsRejected(): void {
		$container = $this->createMock(IContainer::class);
		$container->expects($this->once())->method('get')->with(AiUsageReportingSettingsSeeder::class)->willReturn(new \stdClass());
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Invalid MissionBay AI usage reporting settings seeder service.');
		(new AiUsageReportingSettingsSeedListener($container))->handle('bootstrap.migrated');
	}

	public function testRegisteredSeederIsExecutedThroughExistingHook(): void {
		$settings = $this->createMock(ISettingsStore::class);
		$settings->expects($this->once())->method('get')->willReturn(['version' => 2]);
		$settings->expects($this->never())->method('set');
		$seeder = new AiUsageReportingSettingsSeeder($settings, '/unused');
		$container = $this->createMock(IContainer::class);
		$container->expects($this->once())->method('get')->with(AiUsageReportingSettingsSeeder::class)->willReturn($seeder);
		$this->assertNull((new AiUsageReportingSettingsSeedListener($container))->handle('bootstrap.migrated'));
	}
}
