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

namespace MissionBay\Migration;

use Base3\Api\IContainer;
use Base3\Database\Api\IDatabase;
use Base3\Migration\Api\IDatabaseMigrationProvider;

final class MissionBayAiUsageMigrationProvider implements IDatabaseMigrationProvider {

	public function __construct(
		private readonly IContainer $container
	) {}

	public static function getName(): string {
		return 'missionbayaiusagemigrationprovider';
	}

	public function isActive(): bool {
		return $this->container->has(IDatabase::class);
	}

	public function getMigrations(): array {
		return [
			Migration001AnonymizeAiUsage::class
		];
	}
}
