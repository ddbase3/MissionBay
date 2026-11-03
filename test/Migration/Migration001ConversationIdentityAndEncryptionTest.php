<?php declare(strict_types=1);

namespace MissionBay\Test\Migration;

use Base3\Database\Api\IDatabase;
use MissionBay\Migration\Migration001ConversationIdentityAndEncryption;
use PHPUnit\Framework\TestCase;

final class Migration001ConversationIdentityAndEncryptionTest extends TestCase {

	public function testExistingTablesAreExtendedWithoutDataDeletion(): void {
		$db = new ConversationMigrationDatabaseStub(true);
		(new Migration001ConversationIdentityAndEncryption($db))->up();
		$this->assertContains('owner_type', $db->columns['base3_missionbay_conversation']);
		$this->assertContains('owner_id', $db->columns['base3_missionbay_conversation']);
		$this->assertContains('encryption_method', $db->columns['base3_missionbay_conversation']);
		$this->assertContains('encryption_key_id', $db->columns['base3_missionbay_conversation_message']);
		$this->assertContains('uq_mb_conversation_owner', $db->indexes['base3_missionbay_conversation']);
		$this->assertStringNotContainsString('DELETE ', implode("\n", $db->queries));
		$this->assertStringNotContainsString('DROP ', implode("\n", $db->queries));
	}

	public function testNewTablesAreCreatedByMigrationAndNotMemoryConstructor(): void {
		$db = new ConversationMigrationDatabaseStub(false);
		(new Migration001ConversationIdentityAndEncryption($db))->up();
		$this->assertArrayHasKey('base3_missionbay_conversation', $db->columns);
		$this->assertArrayHasKey('base3_missionbay_conversation_message', $db->columns);
		$this->assertCount(2, array_filter($db->queries,
			static fn(string $q): bool => str_starts_with($q, 'CREATE TABLE ')));
	}
}

require_once __DIR__ . '/ConversationMigrationDatabaseStub.php';
