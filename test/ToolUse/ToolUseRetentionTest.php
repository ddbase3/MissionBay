<?php declare(strict_types=1);

namespace MissionBay\Test\ToolUse;

use Base3\Database\Api\IDatabase;
use MissionBay\ToolUse\ToolUseRetention;
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
