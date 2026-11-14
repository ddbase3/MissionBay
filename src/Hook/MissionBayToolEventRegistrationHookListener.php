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

namespace Base3\MissionBay\Hook;

use Base3\CoreFoundation\Api\IContainer;
use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\CoreFoundation\Hook\Api\IHookListener;
use Base3\CoreFoundation\Usermanager\Api\IUsermanager;
use Base3\MissionBay\Event\MissionBayAgentActionAuditEvent;
use Base3\MissionBay\Event\MissionBayToolFailedEvent;
use Base3\MissionBay\Event\MissionBayToolFinishedEvent;
use Base3\MissionBay\Event\MissionBayToolStartedEvent;
use Base3\MissionBay\Listener\MissionBayToolEventDisplayListener;
use Base3\MissionBay\MissionBayPlugin;

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


		$eventManager->on(MissionBayAgentActionAuditEvent::class, function(MissionBayAgentActionAuditEvent $event): void {
			$this->getToolEventDisplayListener()->onAgentActionAudit($event);
		});

		$this->registered = true;

		return null;
	}

	private function getToolEventDisplayListener(): MissionBayToolEventDisplayListener {
		return $this->container->get(MissionBayToolEventDisplayListener::class);
	}
}
