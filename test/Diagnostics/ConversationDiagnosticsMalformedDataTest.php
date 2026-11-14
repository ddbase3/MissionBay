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

namespace Base3\MissionBay\Test\Diagnostics;

use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\CoreFoundation\Usermanager\Api\IUsermanager;
use Base3\MissionBay\Diagnostics\ConversationMissingChatObjectDiagnosticCheck;
use Base3\MissionBay\Diagnostics\ConversationMissingUserDiagnosticCheck;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/ConversationDiagnosticsDatabaseStub.php';

final class ConversationDiagnosticsMalformedDataTest extends TestCase {

	public function testUserInventoryRejectsNonArrayRows(): void {
		$db = new ConversationDiagnosticsDatabaseStub();
		$db->owners = ['invalid-row'];
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Invalid conversation diagnostic owner row.');
		(new ConversationMissingUserDiagnosticCheck($db, $this->createStub(IUsermanager::class)))->run();
	}

	public function testUserInventoryRejectsMissingOwnerIdentifier(): void {
		$db = new ConversationDiagnosticsDatabaseStub();
		$db->owners = [['owner_id' => null, 'messages' => 7]];
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Conversation diagnostic owner ID is missing.');
		(new ConversationMissingUserDiagnosticCheck($db, $this->createStub(IUsermanager::class)))->run();
	}

	public function testChatObjectInventoryRejectsNonArrayRows(): void {
		$db = new ConversationDiagnosticsDatabaseStub();
		$db->chatObjects = ['invalid-row'];
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Invalid chatbot diagnostic reference row.');
		(new ConversationMissingChatObjectDiagnosticCheck($db, $this->createStub(ISettingsStore::class)))->run();
	}

	public function testUnknownChatObjectDoesNotCheckSettings(): void {
		$db = new ConversationDiagnosticsDatabaseStub();
		$db->chatObjects = [['chatbot_config_group' => '', 'chatbot_config_name' => 'entry']];
		$settings = $this->createMock(ISettingsStore::class);
		$settings->expects($this->never())->method('has');
		$result = (new ConversationMissingChatObjectDiagnosticCheck($db, $settings))->run();
		$this->assertSame(1, $result->details['unresolvedChatObjects']);
		$this->assertSame(0, $result->details['orphanMessages']);
	}

	public function testNoUserInventoryIsPassedWithoutRawIdentifiers(): void {
		$db = new ConversationDiagnosticsDatabaseStub();
		$users = $this->createMock(IUsermanager::class);
		$users->expects($this->never())->method('getUserById');
		$result = (new ConversationMissingUserDiagnosticCheck($db, $users))->run();
		$this->assertSame(0, $result->details['unresolvedOwners']);
		$this->assertSame(0, $result->details['legacyConversations']);
	}
}
