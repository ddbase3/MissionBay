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

namespace Base3\MissionBay\Test\Profile;

use Base3\MissionBay\Profile\ToolFilterReport;
use PHPUnit\Framework\TestCase;

final class ToolFilterReportTest extends TestCase {

	public function testEmptyMissingListIsFeasible(): void {
		$report = new ToolFilterReport([]);
		$this->assertTrue($report->isFeasible());
		$this->assertSame([], $report->getMissingRequiredTools());
	}

	public function testAllMissingToolNamesAreRetained(): void {
		$report = new ToolFilterReport(['delete', 'export']);
		$this->assertFalse($report->isFeasible());
		$this->assertSame(['delete', 'export'], $report->getMissingRequiredTools());
	}
}
