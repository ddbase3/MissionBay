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

use Base3\CoreFoundation\Migration\Api\IDatabaseMigration;
use Base3\CoreFoundation\Migration\Api\ILegacyDatabaseMigration;
use Base3\CoreFoundation\Migration\Exception\MigrationException;
use Base3\MissionBay\Migration\Migration001AnonymizeAiUsage;
use Base3\MissionBay\Migration\Migration001ConversationIdentityAndEncryption;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MissionBayLegacyMigrationTest extends TestCase {

	/**
	 * @return iterable<string, array{class-string<IDatabaseMigration>}>
	 */
	public static function historicalMigrations(): iterable {
		yield 'migration001anonymizeaiusage' => [Migration001AnonymizeAiUsage::class];
		yield 'migration001conversationidentityandencryption' => [Migration001ConversationIdentityAndEncryption::class];
	}

	#[DataProvider('historicalMigrations')]
	public function testHistoricalMigrationIsChecksumExemptAndRejectsDown(string $migrationClass): void {
		self::assertTrue(is_subclass_of($migrationClass, IDatabaseMigration::class));
		self::assertTrue(is_subclass_of($migrationClass, ILegacyDatabaseMigration::class));

		// The reverse operation must fail before any constructor dependency is needed.
		$migration = (new ReflectionClass($migrationClass))->newInstanceWithoutConstructor();
		$this->expectException(MigrationException::class);
		$this->expectExceptionMessage('Not implemented');
		$migration->down();
	}
}
