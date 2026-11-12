<?php declare(strict_types=1);

namespace MissionBay\Test\Migration;

use Base3\Migration\Api\IDatabaseMigration;
use Base3\Migration\Api\ILegacyDatabaseMigration;
use Base3\Migration\Exception\MigrationException;
use MissionBay\Migration\Migration001AnonymizeAiUsage;
use MissionBay\Migration\Migration001ConversationIdentityAndEncryption;
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
