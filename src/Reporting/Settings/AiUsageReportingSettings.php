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

namespace MissionBay\Reporting\Settings;

final class AiUsageReportingSettings {

	public const GROUP_DATAHAWK_SOURCE = 'missionbay_reporting_datahawk_source';
	public const GROUP_VIZION = 'missionbay_reporting_vizion';
	public const GROUP_META = 'missionbay_reporting_meta';
	public const META_DEFAULTS = 'ai_usage_defaults';

	private function __construct() {}
}
