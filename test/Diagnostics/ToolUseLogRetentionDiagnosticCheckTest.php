<?php declare(strict_types=1);

namespace MissionBay\Test\Diagnostics;

use Base3\Database\Api\IDatabase;
use Base3\Diagnostics\Dto\DiagnosticCheckResult;
use MissionBay\Diagnostics\ToolUseLogRetentionDiagnosticCheck;
use PHPUnit\Framework\TestCase;

final class ToolUseLogRetentionDiagnosticCheckTest extends TestCase {

	public function testFailsWhenOverdueRowsAreFoundUsingBothTimestamps(): void {
		$database = $this->createMock(IDatabase::class);
		$database->expects($this->once())->method('connect');
		$database->method('connected')->willReturn(true);
		$database->method('singleQuery')->with("SHOW TABLES LIKE 'base3_missionbay_tooluse'")->willReturn(['found' => 1]);
		$database->method('escape')->willReturnCallback(static fn(string $value): string => $value);
		$database->expects($this->once())->method('scalarQuery')->with($this->callback(static function(string $query): bool {
			if(!str_contains($query, 'SELECT COUNT(*) FROM `base3_missionbay_tooluse`')) return false;
			if(!str_contains($query, '`created_at` <')) return false;
			if(!str_contains($query, '`updated_at` <')) return false;
			preg_match_all("/'(\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2})'/", $query, $matches);
			if(count($matches[1]) !== 2 || $matches[1][0] !== $matches[1][1]) return false;
			$actual = strtotime($matches[1][0] . ' UTC');
			return $actual !== false && abs($actual - (time() - 48 * 3600)) <= 3;
		}))->willReturn('12');
		$result = (new ToolUseLogRetentionDiagnosticCheck($database))->run();
		$this->assertSame(DiagnosticCheckResult::FAILED, $result->status);
		$this->assertSame(['overdue' => 12, 'thresholdHours' => 48], $result->details);
		$this->assertSame('remediation', $result->remediationKey);
	}

	public function testPassesOnlyAfterAValidEmptyCount(): void {
		$database = $this->createMock(IDatabase::class);
		$database->method('connected')->willReturn(true);
		$database->method('singleQuery')->willReturn(['found' => 1]);
		$database->method('escape')->willReturnCallback(static fn(string $value): string => $value);
		$database->method('scalarQuery')->willReturn(0);
		$result = (new ToolUseLogRetentionDiagnosticCheck($database))->run();
		$this->assertSame(DiagnosticCheckResult::PASSED, $result->status);
	}

	public function testMissingTableDoesNotProduceFalseSuccess(): void {
		$database = $this->createMock(IDatabase::class);
		$database->method('connected')->willReturn(true);
		$database->method('singleQuery')->willReturn(null);
		$database->expects($this->never())->method('scalarQuery');
		$result = (new ToolUseLogRetentionDiagnosticCheck($database))->run();
		$this->assertSame(DiagnosticCheckResult::NOT_APPLICABLE, $result->status);
	}

	public function testDatabaseUnavailableIsAnErrorNotSuccess(): void {
		$database = $this->createMock(IDatabase::class);
		$database->method('connected')->willReturn(false);
		$this->expectException(\RuntimeException::class);
		(new ToolUseLogRetentionDiagnosticCheck($database))->run();
	}

}
