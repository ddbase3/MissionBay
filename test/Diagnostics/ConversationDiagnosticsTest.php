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

use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\CoreFoundation\Diagnostics\Dto\DiagnosticCheckResult;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\CoreFoundation\Usermanager\Api\IUsermanager;
use Base3\CoreFoundation\Usermanager\User;
use Base3\MissionBay\Diagnostics\ConversationMissingChatObjectDiagnosticCheck;
use Base3\MissionBay\Diagnostics\ConversationMissingUserDiagnosticCheck;
use PHPUnit\Framework\TestCase;

final class ConversationDiagnosticsTest extends TestCase {

	public function testMissingUserIsReportedWithoutExposingNativeIdentifiers(): void {
		$db = new ConversationDiagnosticsDatabaseStub();
		$db->owners = [
			['owner_id' => '42', 'conversations' => 2, 'messages' => 7],
			['owner_id' => '567', 'conversations' => 1, 'messages' => 3]
		];
		$db->legacy = 4;
		$users = $this->createStub(IUsermanager::class);
		$users->method('getUserById')->willReturnCallback(static function (int|string $id): ?User {
			return (string)$id === '42' ? User::fromArray(['id' => 42]) : null;
		});

		$result = (new ConversationMissingUserDiagnosticCheck($db, $users))->run();
		$this->assertSame(DiagnosticCheckResult::WARNING, $result->status);
		$this->assertSame(1, $result->details['unresolvedOwners']);
		$this->assertSame(3, $result->details['suspectMessages']);
		$this->assertSame(4, $result->details['legacyConversations']);
		$this->assertStringNotContainsString('567', (string)json_encode($result));
	}

	public function testAllResolvedUserIdsArePassed(): void {
		$db = new ConversationDiagnosticsDatabaseStub();
		$db->owners = [['owner_id' => '42', 'conversations' => 1, 'messages' => 1]];
		$users = $this->createStub(IUsermanager::class);
		$users->method('getUserById')->willReturn(User::fromArray(['id' => 42]));
		$this->assertSame(DiagnosticCheckResult::PASSED,
			(new ConversationMissingUserDiagnosticCheck($db, $users))->run()->status);
	}

	public function testWrongUserLookupResultIsNotReportedAsVerified(): void {
		$db = new ConversationDiagnosticsDatabaseStub();
		$db->owners = [['owner_id' => '42', 'conversations' => 1, 'messages' => 3]];
		$users = $this->createStub(IUsermanager::class);
		$users->method('getUserById')->willReturn(User::fromArray(['id' => 43]));
		$result = (new ConversationMissingUserDiagnosticCheck($db, $users))->run();
		$this->assertSame(DiagnosticCheckResult::WARNING, $result->status);
		$this->assertSame(1, $result->details['unresolvedOwners']);
	}

	public function testDeletedChatObjectReportsOrphanMessages(): void {
		$db = new ConversationDiagnosticsDatabaseStub();
		$db->chatObjects = [
			['chatbot_config_group' => 'chatbot', 'chatbot_config_name' => 'removed', 'conversations' => 2, 'messages' => 9],
			['chatbot_config_group' => 'chatbot', 'chatbot_config_name' => 'active', 'conversations' => 3, 'messages' => 14]
		];
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('has')->willReturnCallback(static function (string $group, string $name): bool {
			return $group === 'chatbot' && $name === 'active';
		});
		$result = (new ConversationMissingChatObjectDiagnosticCheck($db, $settings))->run();
		$this->assertSame(DiagnosticCheckResult::FAILED, $result->status);
		$this->assertSame(2, $result->details['orphanConversations']);
		$this->assertSame(9, $result->details['orphanMessages']);
		$this->assertStringNotContainsString('removed', (string)json_encode($result));
	}

	public function testUnattributedLegacyChatObjectIsWarning(): void {
		$db = new ConversationDiagnosticsDatabaseStub();
		$db->chatObjects = [[
			'chatbot_config_group' => null, 'chatbot_config_name' => null,
			'conversations' => 1, 'messages' => 2
		]];
		$settings = $this->createMock(ISettingsStore::class);
		$settings->expects($this->never())->method('has');
		$this->assertSame(DiagnosticCheckResult::WARNING,
			(new ConversationMissingChatObjectDiagnosticCheck($db, $settings))->run()->status);
	}

	public function testChecksAreNotApplicableWithoutTables(): void {
		$db = new ConversationDiagnosticsDatabaseStub();
		$db->installed = false;
		$users = $this->createStub(IUsermanager::class);
		$settings = $this->createStub(ISettingsStore::class);
		$this->assertSame(DiagnosticCheckResult::NOT_APPLICABLE,
			(new ConversationMissingUserDiagnosticCheck($db, $users))->run()->status);
		$this->assertSame(DiagnosticCheckResult::NOT_APPLICABLE,
			(new ConversationMissingChatObjectDiagnosticCheck($db, $settings))->run()->status);
	}
}

require_once __DIR__ . '/ConversationDiagnosticsDatabaseStub.php';
