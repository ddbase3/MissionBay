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

namespace Base3\MissionBay\Test\Migration;

use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\MissionBay\Migration\Migration001AnonymizeAiUsage;
use PHPUnit\Framework\TestCase;

final class Migration001AnonymizeAiUsageTest extends TestCase {

	public function testMissingTableDoesNotExecuteAnyModification(): void {
		$queries = [];
		$db = $this->database(0, 0, $queries);
		(new Migration001AnonymizeAiUsage($db))->up();
		$this->assertSame([], $queries);
	}

	public function testExistingTableWithoutLoginColumnAnonymizesIdsOnly(): void {
		$queries = [];
		$db = $this->database(1, 0, $queries);
		(new Migration001AnonymizeAiUsage($db))->up();
		$this->assertCount(1, $queries);
		$this->assertStringContainsString('SET `user_id` = 0', $queries[0]);
	}

	public function testExistingTableAnonymizesBeforeDroppingLoginColumn(): void {
		$queries = [];
		$db = $this->database(1, 1, $queries);
		(new Migration001AnonymizeAiUsage($db))->up();
		$this->assertCount(2, $queries);
		$this->assertStringContainsString('UPDATE `base3_missionbay_ai_usage`', $queries[0]);
		$this->assertStringContainsString('DROP COLUMN `user_login`', $queries[1]);
	}

	public function testFailedAnonymizationReportsDatabaseErrorWithoutDroppingColumn(): void {
		$queries = [];
		$db = $this->database(1, 1, $queries, true);
		$db->method('errorMessage')->willReturn('write denied');
		try {
			(new Migration001AnonymizeAiUsage($db))->up();
			$this->fail('Migration must stop after a failed write.');
		} catch (\RuntimeException $e) {
			$this->assertStringContainsString('Unable to anonymize', $e->getMessage());
			$this->assertStringContainsString('write denied', $e->getMessage());
		}
		$this->assertCount(1, $queries);
	}

	public function testVersionAndNameRemainStable(): void {
		$queries = [];
		$migration = new Migration001AnonymizeAiUsage($this->database(0, 0, $queries));
		$this->assertSame('001', $migration->getVersion());
		$this->assertSame('missionbay_ai_usage_001_anonymize_user_data', Migration001AnonymizeAiUsage::getName());
		$this->assertStringContainsString('Anonymizes', $migration->getDescription());
	}

	private function database(int $tableCount, int $columnCount, array &$queries, bool $error = false): IDatabase {
		$db = $this->createStub(IDatabase::class);
		$db->method('scalarQuery')->willReturnCallback(static fn(string $sql): int => str_contains($sql, 'information_schema.tables') ? $tableCount : $columnCount);
		$db->method('escape')->willReturnCallback(static fn(string $column): string => $column);
		$db->method('nonQuery')->willReturnCallback(static function(string $query) use (&$queries): void {
			$queries[] = $query;
		});
		$db->method('isError')->willReturn($error);
		return $db;
	}
}
