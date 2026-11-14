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

namespace Base3\MissionBay\Test\Resource;

use Base3\AssistantFoundation\Dto\AgentConversationScope;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Usermanager\Api\IUsermanager;
use Base3\CoreFoundation\Usermanager\User;
use Base3\Core\Crypt\Sodium\SodiumCrypt;
use Base3\CredentialFoundation\Api\IEncryptionProfileService;
use Base3\CredentialFoundation\Dto\EncryptionKeyMaterial;
use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Resource\DatabaseMemoryAgentResource;
use PHPUnit\Framework\TestCase;

final class ConversationDatabaseStub implements IDatabase {

	public array $queries = [];
	public array $createQueries = [];
	public ?array $conversation = null;
	public array $messages = [];
	public int $transactionCalls = 0;
	public int $insertIdCalls = 0;
	public int $affectedRowsCalls = 0;
	public int $errorStateCalls = 0;

	public function connect(): void {}
	public function connected(): bool { return true; }
	public function disconnect(): void {}
	public function beginTransaction(): void { $this->transactionCalls++; throw new \RuntimeException('Transactions are forbidden in this test.'); }
	public function commit(): void { $this->transactionCalls++; throw new \RuntimeException('Transactions are forbidden in this test.'); }
	public function rollback(): void { $this->transactionCalls++; throw new \RuntimeException('Transactions are forbidden in this test.'); }

	public function nonQuery(string $query): void {
		$this->queries[] = $query;
		if (str_starts_with($query, 'CREATE TABLE IF NOT EXISTS ')) {
			$this->createQueries[] = $query;
			return;
		}
		if (str_starts_with($query, 'INSERT INTO base3_missionbay_conversation ')) {
			$values = $this->values($query);
			$this->conversation = [
				'conversation_key' => $values[0],
				'owner_type' => $values[1],
				'owner_id' => $values[2],
				'channel_id' => $values[3],
				'memory_namespace' => $values[4],
				'conversation_id' => $values[5],
				'chatbot_config_group' => $values[6],
				'chatbot_config_name' => $values[7],
				'title' => $values[8],
				'title_source' => $values[9],
				'opening_message' => $values[10],
				'encryption_method' => $values[11],
				'encryption_key_id' => $values[12] === '' ? null : $values[12],
				'created_at' => $values[13],
				'updated_at' => $values[14],
				'last_active_at' => $values[15]
			];
			return;
		}
		if (str_starts_with($query, 'INSERT INTO base3_missionbay_conversation_message ')) {
			$values = $this->values($query);
			$this->messages[$values[0]] = [
				'message_key' => $values[0],
				'conversation_key' => $values[1],
				'node_id' => $values[2],
				'message_id' => $values[3],
				'role' => $values[4],
				'content' => $values[5],
				'payload' => $values[6] === '' ? null : $values[6],
				'encryption_method' => $values[7],
				'encryption_key_id' => $values[8] === '' ? null : $values[8],
				'created_at' => $values[9]
			];
			return;
		}
		if (str_starts_with($query, 'UPDATE base3_missionbay_conversation SET owner_type=')) {
			$old = $this->conditionValue($query, 'owner_key');
			if (($this->conversation['owner_type'] ?? null) === null && ($this->conversation['owner_key'] ?? null) === $old) {
				$this->conversation['owner_type'] = $this->conditionValue($query, 'owner_type');
				$this->conversation['owner_id'] = $this->conditionValue($query, 'owner_id');
			}
			return;
		}
		if (str_starts_with($query, 'UPDATE base3_missionbay_conversation SET title=')) {
			$this->conversation['title'] = $this->conditionValue($query, 'title');
			$this->conversation['title_source'] = $this->conditionValue($query, 'title_source');
			$this->conversation['updated_at'] = $this->conditionValue($query, 'updated_at');
			return;
		}
		if (str_starts_with($query, 'UPDATE base3_missionbay_conversation SET last_active_at=')) {
			$this->conversation['last_active_at'] = $this->conditionValue($query, 'last_active_at');
			return;
		}
		if (str_starts_with($query, 'UPDATE base3_missionbay_conversation SET updated_at=')) {
			$this->conversation['updated_at'] = $this->conditionValue($query, 'updated_at');
			$this->conversation['last_active_at'] = $this->conditionValue($query, 'last_active_at');
			return;
		}
		if (str_starts_with($query, 'DELETE FROM base3_missionbay_conversation WHERE ')) {
			$this->conversation = null;
			$this->messages = [];
			return;
		}
		if (str_starts_with($query, 'DELETE FROM base3_missionbay_conversation_message WHERE conversation_key=')) {
			$nodeId = $this->conditionValue($query, 'node_id');
			foreach ($this->messages as $key => $message) {
				if ($nodeId === '' || $message['node_id'] === $nodeId) unset($this->messages[$key]);
			}
			return;
		}
		if (str_starts_with($query, 'UPDATE base3_missionbay_conversation_message SET payload=')) {
			$key = $this->conditionValue($query, 'message_key');
			if (isset($this->messages[$key])) $this->messages[$key]['payload'] = $this->conditionValue($query, 'payload');
		}
	}

	public function scalarQuery(string $query): mixed { return null; }

	public function singleQuery(string $query): ?array {
		$this->queries[] = $query;
		if (str_contains($query, 'WHERE owner_type IS NULL AND owner_key=')) {
			if (($this->conversation['owner_type'] ?? null) === null
				&& ($this->conversation['owner_key'] ?? null) === $this->conditionValue($query, 'owner_key')) {
				return $this->conversation;
			}
			return null;
		}
		if (str_contains($query, 'FROM base3_missionbay_conversation ')) {
			if ($this->conversation === null) return null;
			$id = $this->conditionValue($query, 'conversation_id');
			$ownerType = $this->conditionValue($query, 'owner_type');
			$ownerId = $this->conditionValue($query, 'owner_id');
			$channel = $this->conditionValue($query, 'channel_id');
			$namespace = $this->conditionValue($query, 'memory_namespace');
			if ($id !== '' && $this->conversation['conversation_id'] !== $id) return null;
			if ($ownerType !== '' && $this->conversation['owner_type'] !== $ownerType) return null;
			if ($ownerId !== '' && $this->conversation['owner_id'] !== $ownerId) return null;
			if ($channel !== '' && $this->conversation['channel_id'] !== $channel) return null;
			if ($namespace !== '' && $this->conversation['memory_namespace'] !== $namespace) return null;
			return $this->conversation;
		}
		if (str_contains($query, 'SELECT message_key FROM base3_missionbay_conversation_message')) {
			$key = $this->conditionValue($query, 'message_key');
			return isset($this->messages[$key]) ? ['message_key' => $key] : null;
		}
		if (str_contains($query, 'SELECT message_key, payload, encryption_method, encryption_key_id FROM base3_missionbay_conversation_message')) {
			foreach ($this->messages as $message) {
				if ($message['message_id'] === $this->conditionValue($query, 'message_id')) return $message;
			}
			return null;
		}
		if (str_contains($query, 'SELECT payload, encryption_method, encryption_key_id FROM base3_missionbay_conversation_message')) {
			$key = $this->conditionValue($query, 'message_key');
			return isset($this->messages[$key]) ? $this->messages[$key] : null;
		}
		return null;
	}

	public function &listQuery(string $query): array { $rows = []; return $rows; }

	public function &multiQuery(string $query): array {
		$this->queries[] = $query;
		if (str_contains($query, 'FROM base3_missionbay_conversation_message')) {
			$rows = [];
			$nodeId = $this->conditionValue($query, 'node_id');
			foreach ($this->messages as $message) {
				if ($nodeId !== '' && $message['node_id'] !== $nodeId) continue;
				$rows[] = $message;
			}
			return $rows;
		}
		if (str_contains($query, 'FROM base3_missionbay_conversation')) {
			$rows = $this->conversation === null ? [] : [$this->conversation];
			return $rows;
		}
		$rows = [];
		return $rows;
	}

	public function affectedRows(): int { $this->affectedRowsCalls++; throw new \RuntimeException('affectedRows() is forbidden in this test.'); }
	public function insertId(): int|string { $this->insertIdCalls++; throw new \RuntimeException('insertId() is forbidden in this test.'); }
	public function escape(string $str): string { return addslashes($str); }
	public function isError(): bool { $this->errorStateCalls++; throw new \RuntimeException('Error state is forbidden in this test.'); }
	public function errorNumber(): int { $this->errorStateCalls++; throw new \RuntimeException('Error state is forbidden in this test.'); }
	public function errorMessage(): string { $this->errorStateCalls++; throw new \RuntimeException('Error state is forbidden in this test.'); }

	/** @return array<int,string|null> */
	private function values(string $query): array {
		$start = strrpos($query, 'VALUES (');
		$input = substr($query, $start + 8, -1);
		$values = [];
		$current = '';
		$quoted = false;
		$escaped = false;
		for ($index = 0, $length = strlen($input); $index < $length; $index++) {
			$char = $input[$index];
			if ($escaped) { $current .= $char; $escaped = false; continue; }
			if ($quoted && $char === '\\') { $current .= $char; $escaped = true; continue; }
			if ($char === "'") { $quoted = !$quoted; continue; }
			if (!$quoted && $char === ',') { $values[] = $this->decode(trim($current)); $current = ''; continue; }
			$current .= $char;
		}
		$values[] = $this->decode(trim($current));
		return $values;
	}

	private function conditionValue(string $query, string $column): string {
		if (preg_match('/(?:SET |, |WHERE | AND )' . preg_quote($column, '/') . "='((?:\\\\.|[^'])*)'/", $query, $match) !== 1) return '';
		return stripslashes($match[1]);
	}

	private function decode(string $value): ?string {
		return strtoupper($value) === 'NULL' ? null : stripslashes($value);
	}
}
