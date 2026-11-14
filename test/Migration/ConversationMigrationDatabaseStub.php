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
