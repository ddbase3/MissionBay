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

namespace MissionBay\Reporting\Hook;

use Base3\Api\IContainer;
use Base3\Hook\Api\IHookListener;
use MissionBay\Reporting\Settings\AiUsageReportingSettingsSeeder;

final class AiUsageReportingSettingsSeedListener implements IHookListener {

	public function __construct(
		private readonly IContainer $container
	) {}

	public static function getSubscribedHooks(): array {
		return [
			'bootstrap.migrated' => 0
		];
	}

	public function isActive(): bool {
		return $this->container->has(AiUsageReportingSettingsSeeder::class);
	}

	public function handle(string $hookName, ...$args) {
		if($hookName !== 'bootstrap.migrated') {
			return null;
		}

		$seeder = $this->container->get(AiUsageReportingSettingsSeeder::class);
		if(!$seeder instanceof AiUsageReportingSettingsSeeder) {
			throw new \RuntimeException('Invalid MissionBay AI usage reporting settings seeder service.');
		}

		$seeder->seed();
		return null;
	}
}
