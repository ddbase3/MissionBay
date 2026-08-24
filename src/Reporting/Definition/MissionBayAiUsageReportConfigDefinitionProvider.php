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

namespace MissionBay\Reporting\Definition;

use Base3\Settings\Api\ISettingsStore;
use MissionBay\Reporting\Settings\AiUsageReportingSettings;
use ResourceFoundation\Api\IReportConfigDefinitionProvider;

final class MissionBayAiUsageReportConfigDefinitionProvider implements IReportConfigDefinitionProvider {

	public function __construct(
		private readonly ISettingsStore $settingsStore
	) {}

	public static function getName(): string {
		return 'missionbayaiusagereportconfigdefinitionprovider';
	}

	public function getScope(): string {
		return 'missionbay_ai';
	}

	public function getDefinitions(): array {
		return $this->settingsStore->getGroup(AiUsageReportingSettings::GROUP_VIZION);
	}
}
