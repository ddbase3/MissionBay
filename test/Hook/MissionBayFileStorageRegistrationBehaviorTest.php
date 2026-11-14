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

namespace Base3\MissionBay\Test\Hook;

use Base3\CoreFoundation\Api\IContainer;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\MissionBay\Hook\MissionBayFileStorageEventRegistrationHookListener;
use Base3\MissionBay\MissionBayPlugin;
use Base3\MissionBay\Service\AiProviderFileOwnerCleanupService;
use Base3\ResourceFoundation\Event\ManagedFileStorageDeletingEvent;
use PHPUnit\Framework\TestCase;

final class MissionBayFileStorageRegistrationBehaviorTest extends TestCase {

	public function testHookIsActiveOnlyForMigratedBootstrap(): void {
		$this->assertSame(['bootstrap.migrated' => 0], MissionBayFileStorageEventRegistrationHookListener::getSubscribedHooks());
		$this->assertTrue((new MissionBayFileStorageEventRegistrationHookListener($this->createStub(IContainer::class)))->isActive());
	}

	public function testUnrelatedHooksDoNotLookUpServices(): void {
		$container = $this->createMock(IContainer::class);
		$container->expects($this->never())->method('has');
		$this->assertNull((new MissionBayFileStorageEventRegistrationHookListener($container))->handle('bootstrap.start'));
	}

	public function testAbsentPluginDoesNotInstallEventListeners(): void {
		$container = $this->createStub(IContainer::class);
		$container->method('has')->willReturn(false);
		$this->assertNull((new MissionBayFileStorageEventRegistrationHookListener($container))->handle('bootstrap.migrated'));
	}

	public function testActivePluginWithoutEventManagerFailsExplicitly(): void {
		$container = $this->container([MissionBayPlugin::getName() => true]);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('requires IEventManager');
		(new MissionBayFileStorageEventRegistrationHookListener($container))->handle('bootstrap.migrated');
	}

	public function testActivePluginWithoutCleanupServiceFailsExplicitly(): void {
		$container = $this->container([MissionBayPlugin::getName() => true, IEventManager::class => true]);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('cleanup service is not registered');
		(new MissionBayFileStorageEventRegistrationHookListener($container))->handle('bootstrap.migrated');
	}

	public function testListenerIsRegisteredExactlyOnceOnRepeatedBootstrap(): void {
		$seen = [];
		$events = $this->createMock(IEventManager::class);
		$events->expects($this->once())->method('on')->willReturnCallback(static function(string $event, callable $listener) use (&$seen): void {
			$seen[] = $event;
		});
		$container = $this->container([
			MissionBayPlugin::getName() => true,
			IEventManager::class => true,
			AiProviderFileOwnerCleanupService::class => true
		], $events);
		$hook = new MissionBayFileStorageEventRegistrationHookListener($container);
		$hook->handle('bootstrap.migrated');
		$hook->handle('bootstrap.migrated');
		$this->assertSame([ManagedFileStorageDeletingEvent::class], $seen);
	}

	private function container(array $registered, ?IEventManager $events = null): IContainer {
		$container = $this->createStub(IContainer::class);
		$container->method('has')->willReturnCallback(static fn(string $name): bool => $registered[$name] ?? false);
		if($events !== null) {
			$container->method('get')->willReturnCallback(static fn(string $name): mixed => $name === IEventManager::class ? $events : null);
		}
		return $container;
	}
}
