<?php declare(strict_types=1);

namespace MissionBay\Test\Profile;

use MissionBay\Profile\ToolFilterReport;
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
