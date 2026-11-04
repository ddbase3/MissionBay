<?php declare(strict_types=1);

namespace MissionBay\Test\Hook;

use Base3\Api\IContainer;
use Base3\Database\Api\IDatabase;
use Base3\Event\Api\IEventManager;
use Base3\Usermanager\Api\IUsermanager;
use MissionBay\Event\MissionBayAgentActionAuditEvent;
use MissionBay\Event\MissionBayToolFailedEvent;
use MissionBay\Event\MissionBayToolFinishedEvent;
use MissionBay\Event\MissionBayToolStartedEvent;
use MissionBay\Hook\MissionBayToolEventRegistrationHookListener;
use MissionBay\Listener\MissionBayToolEventDisplayListener;
use MissionBay\MissionBayPlugin;
use PHPUnit\Framework\TestCase;

final class MissionBayToolEventRegistrationHookListenerCoverageTest extends TestCase {

	private function registration(array $missing, array &$events): MissionBayToolEventRegistrationHookListener {
		$required = [
			MissionBayPlugin::getName(),
			IEventManager::class,
			IDatabase::class,
			IUsermanager::class,
			MissionBayToolEventDisplayListener::class
		];
		$container = $this->createStub(IContainer::class);
		$container->method('has')->willReturnCallback(static function (string $name) use ($required, $missing): bool {
			return in_array($name, $required, true) && !in_array($name, $missing, true);
		});
		$manager = $this->createStub(IEventManager::class);
		$manager->method('on')->willReturnCallback(static function (string $event, callable $callback, int $priority = 0) use (&$events): void {
			$events[] = [$event, $callback, $priority];
		});
		$container->method('get')->willReturn($manager);
		return new MissionBayToolEventRegistrationHookListener($container);
	}

	public function testHookIsAlwaysActive(): void {
		$events = [];
		$this->assertTrue($this->registration([], $events)->isActive());
	}

	public function testHooksOnlySubscribeAfterMigration(): void {
		$this->assertSame(['bootstrap.migrated' => 0], MissionBayToolEventRegistrationHookListener::getSubscribedHooks());
	}

	public function testUnrelatedHookCannotRegisterEvents(): void {
		$events = [];
		$this->registration([], $events)->handle('bootstrap.start');
		$this->assertSame([], $events);
	}

	public function testMissingPluginPreventsRegistration(): void {
		$events = [];
		$this->registration([MissionBayPlugin::getName()], $events)->handle('bootstrap.migrated');
		$this->assertSame([], $events);
	}

	public function testMissingEventManagerPreventsRegistration(): void {
		$events = [];
		$this->registration([IEventManager::class], $events)->handle('bootstrap.migrated');
		$this->assertSame([], $events);
	}

	public function testMissingDatabasePreventsRegistration(): void {
		$events = [];
		$this->registration([IDatabase::class], $events)->handle('bootstrap.migrated');
		$this->assertSame([], $events);
	}

	public function testMissingUsermanagerPreventsRegistration(): void {
		$events = [];
		$this->registration([IUsermanager::class], $events)->handle('bootstrap.migrated');
		$this->assertSame([], $events);
	}

	public function testMissingDisplayListenerPreventsRegistration(): void {
		$events = [];
		$this->registration([MissionBayToolEventDisplayListener::class], $events)->handle('bootstrap.migrated');
		$this->assertSame([], $events);
	}

	public function testRegisteringAllEventsUsesExistingEventManager(): void {
		$events = [];
		$listener = $this->registration([], $events);
		$listener->handle('bootstrap.migrated');
		$this->assertSame([
			MissionBayToolStartedEvent::class,
			MissionBayToolFinishedEvent::class,
			MissionBayToolFailedEvent::class,
			MissionBayAgentActionAuditEvent::class
		], array_column($events, 0));
		foreach ($events as $entry) {
			$this->assertSame(0, $entry[2]);
		}
	}

	public function testRegistrationIsIdempotentAcrossRepeatedMigrationHooks(): void {
		$events = [];
		$listener = $this->registration([], $events);
		$listener->handle('bootstrap.migrated');
		$listener->handle('bootstrap.migrated');
		$this->assertCount(4, $events);
	}
}
