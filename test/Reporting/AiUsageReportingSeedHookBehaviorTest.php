<?php declare(strict_types=1);

namespace MissionBay\Test\Reporting;

use Base3\Api\IContainer;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Reporting\Hook\AiUsageReportingSettingsSeedListener;
use MissionBay\Reporting\Settings\AiUsageReportingSettingsSeeder;
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
