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

namespace MissionBay\Hook;

use Base3\Api\IContainer;
use Base3\Database\Api\IDatabase;
use Base3\Event\Api\IEventManager;
use Base3\Hook\Api\IHookListener;
use Base3\Usermanager\Api\IUsermanager;
use MissionBay\Event\MissionBayToolFailedEvent;
use MissionBay\Event\MissionBayToolFinishedEvent;
use MissionBay\Event\MissionBayToolStartedEvent;
use MissionBay\Listener\MissionBayToolEventDisplayListener;
use MissionBay\MissionBayPlugin;

final class MissionBayToolEventRegistrationHookListener implements IHookListener {

	private bool $registered = false;

	public function __construct(
		private readonly IContainer $container
	) {}

	public static function getSubscribedHooks(): array {
		return [
			'bootstrap.migrated' => 0
		];
	}

	public function isActive(): bool {
		return true;
	}

	public function handle(string $hookName, ...$args) {
		if ($hookName !== 'bootstrap.migrated') {
			return null;
		}

		if ($this->registered) {
			return null;
		}

		if (!$this->container->has(MissionBayPlugin::getName())) {
			return null;
		}

		if (!$this->container->has(IEventManager::class)) {
			return null;
		}

		if (!$this->container->has(IDatabase::class) || !$this->container->has(IUsermanager::class)) {
			return null;
		}

		if (!$this->container->has(MissionBayToolEventDisplayListener::class)) {
			return null;
		}

		$eventManager = $this->container->get(IEventManager::class);

		$eventManager->on(MissionBayToolStartedEvent::class, function(MissionBayToolStartedEvent $event): void {
			$this->getToolEventDisplayListener()->onToolStarted($event);
		});

		$eventManager->on(MissionBayToolFinishedEvent::class, function(MissionBayToolFinishedEvent $event): void {
			$this->getToolEventDisplayListener()->onToolFinished($event);
		});

		$eventManager->on(MissionBayToolFailedEvent::class, function(MissionBayToolFailedEvent $event): void {
			$this->getToolEventDisplayListener()->onToolFailed($event);
		});

		$this->registered = true;

		return null;
	}

	private function getToolEventDisplayListener(): MissionBayToolEventDisplayListener {
		return $this->container->get(MissionBayToolEventDisplayListener::class);
	}
}
