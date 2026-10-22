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

final class ConversationMigrationDatabaseStub implements IDatabase {

	/** @var array<string,list<string>> */
	public array $columns = [];
	/** @var array<string,list<string>> */
	public array $indexes = [];
	public array $queries = [];
	private array $result = [];

	public function __construct(bool $existing) {
		if ($existing) {
			$this->columns['base3_missionbay_conversation'] = [
				'conversation_key','owner_key','channel_id','memory_namespace','conversation_id','title',
				'title_source','opening_message','created_at','updated_at','last_active_at'
			];
			$this->columns['base3_missionbay_conversation_message'] = [
				'message_key','conversation_key','node_id','message_id','role','content','payload','created_at'
			];
			$this->indexes['base3_missionbay_conversation'] = ['PRIMARY','uq_mb_conversation_key'];
			$this->indexes['base3_missionbay_conversation_message'] = ['PRIMARY','uq_mb_conversation_message'];
		}
	}

	public function connect(): void {}
	public function connected(): bool { return true; }
	public function disconnect(): void {}
	public function beginTransaction(): void {}
	public function commit(): void {}
	public function rollback(): void {}
	public function nonQuery(string $query): void {
		$this->queries[] = $query;
		if (preg_match('/^CREATE TABLE `([^`]+)`/', $query, $matches)) {
			$this->columns[$matches[1]] = ['owner_type','owner_id','encryption_method','encryption_key_id'];
			$this->indexes[$matches[1]] = ['uq_mb_conversation_owner'];
		}
		elseif (preg_match('/^ALTER TABLE `([^`]+)` ADD COLUMN `([^`]+)`/', $query, $matches)) {
			$this->columns[$matches[1]][] = $matches[2];
		}
		elseif (preg_match('/^ALTER TABLE `([^`]+)` ADD (?:UNIQUE )?KEY `([^`]+)`/', $query, $matches)) {
			$this->indexes[$matches[1]][] = $matches[2];
		}
	}
	public function scalarQuery(string $query): mixed {
		preg_match("/table_name = '([^']+)'/", $query, $table);
		preg_match("/column_name = '([^']+)'/", $query, $column);
		preg_match("/index_name = '([^']+)'/", $query, $index);
		$t = $table[1] ?? '';
		if (str_contains($query, 'information_schema.tables')) return isset($this->columns[$t]) ? 1 : 0;
		if (str_contains($query, 'information_schema.columns')) return in_array($column[1] ?? '', $this->columns[$t] ?? [], true) ? 1 : 0;
		if (str_contains($query, 'information_schema.statistics')) return in_array($index[1] ?? '', $this->indexes[$t] ?? [], true) ? 1 : 0;
		throw new \RuntimeException('Unexpected migration SQL.');
	}
	public function singleQuery(string $query): ?array { return null; }
	public function &listQuery(string $query): array { return $this->result; }
	public function &multiQuery(string $query): array { return $this->result; }
	public function affectedRows(): int { return 0; }
	public function insertId(): int|string { return 0; }
	public function escape(string $str): string { return addslashes($str); }
	public function isError(): bool { return false; }
	public function errorNumber(): int { return 0; }
	public function errorMessage(): string { return ''; }
}
