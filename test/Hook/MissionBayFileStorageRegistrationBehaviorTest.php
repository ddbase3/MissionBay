<?php declare(strict_types=1);

namespace MissionBay\Test\Hook;

use Base3\Api\IContainer;
use Base3\Event\Api\IEventManager;
use MissionBay\Hook\MissionBayFileStorageEventRegistrationHookListener;
use MissionBay\MissionBayPlugin;
use MissionBay\Service\AiProviderFileOwnerCleanupService;
use ResourceFoundation\Event\ManagedFileStorageDeletingEvent;
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
