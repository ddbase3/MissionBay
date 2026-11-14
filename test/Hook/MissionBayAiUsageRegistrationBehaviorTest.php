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

use Base3\AssistantFoundation\Event\AiProviderRequestCompletedEvent;
use Base3\CoreFoundation\Api\IContainer;
use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MissionBay\Hook\MissionBayAiUsageEventRegistrationHookListener;
use Base3\MissionBay\Listener\MissionBayAiUsageLogListener;
use Base3\MissionBay\MissionBayPlugin;
use PHPUnit\Framework\TestCase;

final class MissionBayAiUsageRegistrationBehaviorTest extends TestCase {

	public function testHookMetadataAndActivationAreStable(): void {
		$this->assertSame(['bootstrap.migrated' => 0], MissionBayAiUsageEventRegistrationHookListener::getSubscribedHooks());
		$this->assertTrue((new MissionBayAiUsageEventRegistrationHookListener($this->createStub(IContainer::class)))->isActive());
	}

	public function testUnrelatedHooksDoNotAskForPluginRegistration(): void {
		$container = $this->createMock(IContainer::class);
		$container->expects($this->never())->method('has');
		$this->assertNull((new MissionBayAiUsageEventRegistrationHookListener($container))->handle('bootstrap.start'));
	}

	public function testMissingPluginDoesNotRegisterEventListener(): void {
		$container = $this->createStub(IContainer::class);
		$container->method('has')->willReturn(false);
		$this->assertNull((new MissionBayAiUsageEventRegistrationHookListener($container))->handle('bootstrap.migrated'));
	}

	public function testMissingLoggerStopsOptionalUsageRegistration(): void {
		$container = $this->container([
			MissionBayPlugin::getName() => true,
			IEventManager::class => true,
			IDatabase::class => true
		]);
		$this->assertNull((new MissionBayAiUsageEventRegistrationHookListener($container))->handle('bootstrap.migrated'));
	}

	public function testListenerIsRegisteredOnceWhenAllDependenciesAreAvailable(): void {
		$seen = [];
		$events = $this->createMock(IEventManager::class);
		$events->expects($this->once())->method('on')->willReturnCallback(static function(string $event, callable $listener) use (&$seen): void {
			$seen[] = $event;
		});
		$container = $this->container([
			MissionBayPlugin::getName() => true,
			IEventManager::class => true,
			IDatabase::class => true,
			ILogger::class => true,
			MissionBayAiUsageLogListener::class => true
		], $events);
		$hook = new MissionBayAiUsageEventRegistrationHookListener($container);
		$hook->handle('bootstrap.migrated');
		$hook->handle('bootstrap.migrated');
		$this->assertSame([AiProviderRequestCompletedEvent::class], $seen);
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
