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
use Base3\MissionBay\Migration\Migration001ConversationIdentityAndEncryption;
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
