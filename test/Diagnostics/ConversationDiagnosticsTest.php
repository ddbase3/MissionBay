<?php declare(strict_types=1);

namespace MissionBay\Test\Diagnostics;

use Base3\Database\Api\IDatabase;
use Base3\Diagnostics\Dto\DiagnosticCheckResult;
use Base3\Settings\Api\ISettingsStore;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\User;
use MissionBay\Diagnostics\ConversationMissingChatObjectDiagnosticCheck;
use MissionBay\Diagnostics\ConversationMissingUserDiagnosticCheck;
use PHPUnit\Framework\TestCase;

final class ConversationDiagnosticsTest extends TestCase {

	public function testMissingUserIsReportedWithoutExposingNativeIdentifiers(): void {
		$db = new ConversationDiagnosticsDatabaseStub();
		$db->owners = [
			['owner_id' => '42', 'conversations' => 2, 'messages' => 7],
			['owner_id' => '567', 'conversations' => 1, 'messages' => 3]
		];
		$db->legacy = 4;
		$users = $this->createMock(IUsermanager::class);
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
		$users = $this->createMock(IUsermanager::class);
		$users->method('getUserById')->willReturn(User::fromArray(['id' => 42]));
		$this->assertSame(DiagnosticCheckResult::PASSED,
			(new ConversationMissingUserDiagnosticCheck($db, $users))->run()->status);
	}

	public function testWrongUserLookupResultIsNotReportedAsVerified(): void {
		$db = new ConversationDiagnosticsDatabaseStub();
		$db->owners = [['owner_id' => '42', 'conversations' => 1, 'messages' => 3]];
		$users = $this->createMock(IUsermanager::class);
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
		$settings = $this->createMock(ISettingsStore::class);
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
		$users = $this->createMock(IUsermanager::class);
		$settings = $this->createMock(ISettingsStore::class);
		$this->assertSame(DiagnosticCheckResult::NOT_APPLICABLE,
			(new ConversationMissingUserDiagnosticCheck($db, $users))->run()->status);
		$this->assertSame(DiagnosticCheckResult::NOT_APPLICABLE,
			(new ConversationMissingChatObjectDiagnosticCheck($db, $settings))->run()->status);
	}
}

final class ConversationDiagnosticsDatabaseStub implements IDatabase {

	public bool $installed = true;
	public int $legacy = 0;
	/** @var array<int,array<string,mixed>> */
	public array $owners = [];
	/** @var array<int,array<string,mixed>> */
	public array $chatObjects = [];
	private array $result = [];

	public function connect(): void {}
	public function connected(): bool { return true; }
	public function disconnect(): void {}
	public function beginTransaction(): void {}
	public function commit(): void {}
	public function rollback(): void {}
	public function nonQuery(string $query): void { throw new \RuntimeException('Diagnostics must never mutate database records.'); }
	public function scalarQuery(string $query): mixed {
		if (str_contains($query, 'information_schema.tables')) return $this->installed ? 1 : 0;
		if (str_contains($query, 'owner_type IS NULL')) return $this->legacy;
		throw new \RuntimeException('Unexpected diagnostic SQL.');
	}
	public function singleQuery(string $query): ?array { throw new \RuntimeException('Unexpected diagnostic SQL.'); }
	public function &listQuery(string $query): array { throw new \RuntimeException('Unexpected diagnostic SQL.'); }
	public function &multiQuery(string $query): array {
		if (str_contains($query, 'GROUP BY c.owner_id')) $this->result = $this->owners;
		elseif (str_contains($query, 'GROUP BY c.chatbot_config_group')) $this->result = $this->chatObjects;
		else throw new \RuntimeException('Unexpected diagnostic SQL.');
		return $this->result;
	}
	public function affectedRows(): int { return 0; }
	public function insertId(): int|string { return 0; }
	public function escape(string $str): string { return addslashes($str); }
	public function isError(): bool { return false; }
	public function errorNumber(): int { return 0; }
	public function errorMessage(): string { return ''; }
}
