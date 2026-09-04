<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Database\Api\IDatabase;
use Base3\Session\Api\ISession;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Context\AgentContext;
use MissionBay\Resource\DatabaseMemoryAgentResource;
use PHPUnit\Framework\TestCase;

final class DatabaseMemoryAgentResourceTest extends TestCase {

	public function testConstructorCreatesOnlyCanonicalTables(): void {
		$database = new ConversationDatabaseStub();
		new DatabaseMemoryAgentResource(
			$database,
			new DatabaseMemoryConfigResolverStub(),
			new DatabaseMemoryAccesscontrolStub(42),
			new DatabaseMemorySessionStub('session-one'),
			'memory-main'
		);

		$this->assertCount(2, $database->createQueries);
		$this->assertStringContainsString('CREATE TABLE IF NOT EXISTS base3_missionbay_conversation ', $database->createQueries[0]);
		$this->assertStringContainsString('CREATE TABLE IF NOT EXISTS base3_missionbay_conversation_message ', $database->createQueries[1]);
		$this->assertStringNotContainsString('ALTER TABLE', implode("\n", $database->queries));
		$this->assertStringNotContainsString('AUTO_INCREMENT', implode("\n", $database->queries));
	}

	public function testConversationAndMessagesUsePreGeneratedKeysWithoutUnsupportedDatabaseMethods(): void {
		$database = new ConversationDatabaseStub();
		$resource = $this->resource($database, 42, 'session-one');
		$resource->init([], $this->context('chatbot-main'));
		$conversation = $resource->createConversation(
			'conversation-one',
			'First chat',
			openingMessage: 'How can I help?'
		);

		$resource->appendNodeHistory('assistant', [
			'id' => 'message-one',
			'role' => 'user',
			'content' => 'Remember ALPHA-739',
			'timestamp' => '2026-07-29T16:00:00+02:00'
		]);
		$resource->appendNodeHistory('assistant', [
			'id' => 'message-two',
			'role' => 'assistant',
			'content' => 'Stored'
		]);

		$this->assertSame('conversation-one', $conversation->getId());
		$this->assertSame([
			[
				'id' => 'message-one',
				'role' => 'user',
				'content' => 'Remember ALPHA-739',
				'timestamp' => '2026-07-29T16:00:00+02:00'
			],
			[
				'id' => 'message-two',
				'role' => 'assistant',
				'content' => 'Stored'
			]
		], $resource->loadNodeHistory('assistant'));
		$this->assertSame(hash('sha256', 'user:42'), $database->conversation['owner_key'] ?? null);
		$this->assertSame('chatbot-main', $database->conversation['channel_id'] ?? null);
		$this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', (string)($database->conversation['conversation_key'] ?? ''));
		$this->assertSame(0, $database->transactionCalls);
		$this->assertSame(0, $database->insertIdCalls);
		$this->assertSame(0, $database->affectedRowsCalls);
		$this->assertSame(0, $database->errorStateCalls);
	}

	public function testMessageMetadataCanBeUpdatedWithoutChangingMessageContent(): void {
		$database = new ConversationDatabaseStub();
		$resource = $this->resource($database, 42, 'session-one');
		$resource->init([], $this->context('chatbot-main'));
		$resource->createConversation('conversation-one');
		$resource->appendNodeHistory('assistant', [
			'id' => 'message-one',
			'role' => 'user',
			'content' => 'Cancelled question'
		]);

		$this->assertTrue($resource->updateNodeHistoryMessageMetadata(
			'assistant',
			'message-one',
			['status' => 'cancelled', 'content' => 'must not replace']
		));
		$this->assertSame([[
			'id' => 'message-one',
			'role' => 'user',
			'content' => 'Cancelled question',
			'status' => 'cancelled'
		]], $resource->loadNodeHistory('assistant'));
	}

	public function testManualTitleIsNotOverwrittenAndDeleteRemovesConversation(): void {
		$database = new ConversationDatabaseStub();
		$resource = $this->resource($database, null, 'session-one');
		$resource->init([], $this->context('chatbot-main'));
		$resource->createConversation('conversation-one', 'Temporary', openingMessage: 'Hello');
		$manual = $resource->renameConversation('conversation-one', 'Manual title');
		$automatic = $resource->renameConversation(
			'conversation-one',
			'Automatic title',
			\AssistantFoundation\Dto\AgentConversation::TITLE_SOURCE_AUTOMATIC
		);

		$this->assertSame('Manual title', $manual->getTitle());
		$this->assertSame('Manual title', $automatic->getTitle());
		$this->assertSame(hash('sha256', 'session:session-one'), $database->conversation['owner_key'] ?? null);

		$resource->deleteConversation('conversation-one');
		$this->assertNull($resource->getConversation('conversation-one'));
		$this->assertSame([], $database->messages);
	}

	public function testConversationScopeRequiresStableChannelId(): void {
		$resource = $this->resource(new ConversationDatabaseStub(), 42, 'session-one');

		$this->expectException(\RuntimeException::class);
		$resource->init([], new AgentContext());
	}

	private function resource(ConversationDatabaseStub $database, mixed $userId, string $sessionId): DatabaseMemoryAgentResource {
		return new DatabaseMemoryAgentResource(
			$database,
			new DatabaseMemoryConfigResolverStub(),
			new DatabaseMemoryAccesscontrolStub($userId),
			new DatabaseMemorySessionStub($sessionId),
			'memory-main'
		);
	}

	private function context(string $channelId): AgentContext {
		$context = new AgentContext();
		$context->setVar('conversation_channel_id', $channelId);
		return $context;
	}
}

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
				'owner_key' => $values[1],
				'channel_id' => $values[2],
				'memory_namespace' => $values[3],
				'conversation_id' => $values[4],
				'title' => $values[5],
				'title_source' => $values[6],
				'opening_message' => $values[7],
				'created_at' => $values[8],
				'updated_at' => $values[9],
				'last_active_at' => $values[10]
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
				'payload' => $values[6],
				'created_at' => $values[7]
			];
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
		if (str_contains($query, 'FROM base3_missionbay_conversation ')) {
			if ($this->conversation === null) return null;
			$id = $this->conditionValue($query, 'conversation_id');
			$owner = $this->conditionValue($query, 'owner_key');
			$channel = $this->conditionValue($query, 'channel_id');
			$namespace = $this->conditionValue($query, 'memory_namespace');
			if ($id !== '' && $this->conversation['conversation_id'] !== $id) return null;
			if ($owner !== '' && $this->conversation['owner_key'] !== $owner) return null;
			if ($channel !== '' && $this->conversation['channel_id'] !== $channel) return null;
			if ($namespace !== '' && $this->conversation['memory_namespace'] !== $namespace) return null;
			return $this->conversation;
		}
		if (str_contains($query, 'SELECT message_key FROM base3_missionbay_conversation_message')) {
			$key = $this->conditionValue($query, 'message_key');
			return isset($this->messages[$key]) ? ['message_key' => $key] : null;
		}
		if (str_contains($query, 'SELECT message_key, payload FROM base3_missionbay_conversation_message')) {
			foreach ($this->messages as $message) {
				if ($message['message_id'] === $this->conditionValue($query, 'message_id')) return $message;
			}
			return null;
		}
		if (str_contains($query, 'SELECT payload FROM base3_missionbay_conversation_message')) {
			$key = $this->conditionValue($query, 'message_key');
			return isset($this->messages[$key]) ? ['payload' => $this->messages[$key]['payload']] : null;
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

	/** @return array<int,string> */
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

	private function decode(string $value): string {
		return strtoupper($value) === 'NULL' ? '' : stripslashes($value);
	}
}

final class DatabaseMemoryConfigResolverStub implements IAgentConfigValueResolver {
	public function resolveValue(array|string|int|float|bool|null $config): mixed { return $config; }
}

final class DatabaseMemoryAccesscontrolStub implements IAccesscontrol {
	public function __construct(private readonly mixed $userId) {}
	public function getUserId() { return $this->userId; }
	public function authenticate(): void {}
}

final class DatabaseMemorySessionStub implements ISession {
	private bool $started = false;
	public function __construct(private readonly string $id) {}
	public function started(): bool { return $this->started; }
	public function getId(): string { return $this->id; }
	public function start(): bool { $this->started = true; return true; }
	public function destroy(): bool { $this->started = false; return true; }
	public function get(string $key, mixed $default = null): mixed { return $default; }
	public function set(string $key, mixed $value): void {}
	public function has(string $key): bool { return false; }
	public function remove(string $key): void {}
}
