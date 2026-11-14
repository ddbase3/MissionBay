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

namespace Base3\MissionBay\Test\ToolUse;

use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\MissionBay\ToolUse\ToolUseRetention;
use PHPUnit\Framework\TestCase;

final class ToolUseRetentionTest extends TestCase {

	public function testCutoffIsUtcWithExpectedRetentionWindow(): void {
		$this->assertSame(24, ToolUseRetention::CLEANUP_HOURS);
		$this->assertSame(48, ToolUseRetention::DIAGNOSTIC_HOURS);
		$cutoff = strtotime(ToolUseRetention::cutoffUtc(24) . ' UTC');
		$this->assertTrue(abs(($cutoff + 86400) - time()) <= 5);
	}

	public function testExistingTableIsQueriedWithoutCreation(): void {
		$database = $this->createStub(IDatabase::class);
		$database->method('singleQuery')->willReturn(['name' => ToolUseRetention::TABLE]);
		$this->assertTrue(ToolUseRetention::tableExists($database));
		$this->assertSame('base3_missionbay_tooluse', ToolUseRetention::TABLE);
	}

	public function testRetentionPredicateRequiresBothTimestamps(): void {
		$database = $this->createStub(IDatabase::class);
		$database->method('escape')->willReturnCallback(static fn(string $value): string => str_replace("'", "''", $value));
		$this->assertSame("`created_at` < '2026-01-01 00:00:00' AND `updated_at` < '2026-01-01 00:00:00'", ToolUseRetention::expiredWhere($database, '2026-01-01 00:00:00'));
		$this->assertStringContainsString("O''Brien", ToolUseRetention::expiredWhere($database, "O'Brien"));
	}
}
