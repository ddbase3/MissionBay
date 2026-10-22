<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use AssistantFoundation\Dto\AgentConversationScope;
use Base3\Api\IClassMap;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\User;
use Base3\Crypt\Sodium\SodiumCrypt;
use Base3\Database\Api\IDatabase;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Context\AgentContext;
use MissionBay\Resource\DatabaseMemoryAgentResource;
use PHPUnit\Framework\TestCase;

final class DatabaseMemoryAgentResourceTest extends TestCase {

	public function testConstructorDoesNotCreateOrAlterTables(): void {
		$database = new ConversationDatabaseStub();
		$resource = $this->resource($database, 42, 'session-one');
		$resource->init([], $this->context('chatbot-main'));
		$this->assertSame([], $database->queries);
		$this->assertSame([], $database->createQueries);
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
		$this->assertSame('user', $database->conversation['owner_type'] ?? null);
		$this->assertSame('42', $database->conversation['owner_id'] ?? null);
		$this->assertArrayNotHasKey('owner_key', $database->conversation ?? []);
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
		$resource = $this->resource($database, 42, 'session-one');
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
		$this->assertSame('user', $database->conversation['owner_type'] ?? null);
		$this->assertSame('42', $database->conversation['owner_id'] ?? null);

		$resource->deleteConversation('conversation-one');
		$this->assertNull($resource->getConversation('conversation-one'));
		$this->assertSame([], $database->messages);
	}

	public function testBulkDeleteRemovesAllConversationsForOwner(): void {
		$database = new ConversationDatabaseStub();
		$resource = $this->resource($database, 42, 'session-one');
		$resource->init([], $this->context('chatbot-main'));
		$resource->createConversation('conversation-one');
		$resource->appendNodeHistory('assistant', ['id' => 'message-one', 'role' => 'user', 'content' => 'Stored']);

		$resource->deleteConversationsByOwner('user', '42');

		$this->assertNull($database->conversation);
		$this->assertSame([], $database->messages);
		$this->assertTrue($this->containsQuery($database->queries, 'DELETE FROM base3_missionbay_conversation WHERE (owner_type='));
	}

	public function testBulkDeleteRemovesAllConversationsForChannel(): void {
		$database = new ConversationDatabaseStub();
		$resource = $this->resource($database, 42, 'session-one');
		$resource->init([], $this->context('chatbot-main'));
		$resource->createConversation('conversation-one');

		$resource->deleteConversationsByChannel('chatbot-main');

		$this->assertNull($database->conversation);
		$this->assertTrue($this->containsQuery($database->queries, 'DELETE FROM base3_missionbay_conversation WHERE channel_id='));
	}

	/** @param array<int,string> $queries */
	private function containsQuery(array $queries, string $needle): bool {
		foreach ($queries as $query) {
			if (str_contains($query, $needle)) {
				return true;
			}
		}

		return false;
	}

	public function testConversationScopeRequiresStableChannelId(): void {
		$resource = $this->resource(new ConversationDatabaseStub(), 42, 'session-one');

		$this->expectException(\RuntimeException::class);
		$resource->init([], new AgentContext());
		$resource->listConversations();
	}

	private function resource(ConversationDatabaseStub $database, mixed $userId, string $sessionId, array $config = [], bool $withSodium = false): DatabaseMemoryAgentResource {
		$userManager = $this->createMock(IUsermanager::class);
		$user = $userId === null ? null : User::fromArray(['id' => $userId]);
		$userManager->method('getUser')->willReturn($user);
		$classmap = $this->createMock(IClassMap::class);
		if ($withSodium) {
			$classmap->method('getInstancesByInterface')->willReturn([new SodiumCrypt()]);
		}
		$resource = new DatabaseMemoryAgentResource(
			$database,
			new DatabaseMemoryConfigResolverStub(),
			$userManager,
			$classmap,
			$this->createMock(\Base3\Translation\Api\ITranslation::class),
			'memory-main'
		);
		$resource->setConfig($config);
		return $resource;
	}

	public function testAnonymousUseOfDatabaseMemoryIsRejectedWithoutWrites(): void {
		$database = new ConversationDatabaseStub();
		$resource = $this->resource($database, null, 'session-one');
		$resource->init([], $this->context('chatbot-main'));
		$this->expectException(\RuntimeException::class);
		$resource->createConversation('conversation-one');
	}

	public function testLegacyOwnerIsReassignedFromKnownNativeId(): void {
		$database = new ConversationDatabaseStub();
		$database->conversation = [
			'conversation_key' => str_repeat('b', 40),
			'owner_key' => hash('sha256', 'user:42'),
			'owner_type' => null, 'owner_id' => null,
			'channel_id' => 'chatbot-main', 'memory_namespace' => 'default',
			'conversation_id' => 'conversation-legacy',
			'title' => 'Earlier conversation', 'title_source' => 'temporary',
			'opening_message' => null, 'encryption_method' => 'none', 'encryption_key_id' => null,
			'created_at' => '2026-08-20 10:00:00.000000',
			'updated_at' => '2026-08-20 10:00:00.000000',
			'last_active_at' => '2026-08-20 10:00:00.000000'
		];
		$resource = $this->resource($database, 42, 'session-one');
		$resource->init([], $this->context('chatbot-main'));
		$this->assertSame('Earlier conversation', $resource->listConversations()[0]->getTitle());
		$this->assertSame('user', $database->conversation['owner_type']);
		$this->assertSame('42', $database->conversation['owner_id']);
	}

	public function testChatbotSourceIsSavedForGlobalObjectDiagnostics(): void {
		$database = new ConversationDatabaseStub();
		$resource = $this->resource($database, 42, 'session-one');
		$context = $this->context('chatbot-main');
		$context->setVar('chatbot_config_group', 'chatbot');
		$context->setVar('chatbot_config_name', 'example');
		$resource->init([], $context);
		$resource->createConversation('conversation-sourced');
		$this->assertSame('chatbot', $database->conversation['chatbot_config_group']);
		$this->assertSame('example', $database->conversation['chatbot_config_name']);
	}

	public function testHostDefinedNativeUserIdZeroIsNotMistakenForAnonymous(): void {
		$database = new ConversationDatabaseStub();
		$resource = $this->resource($database, 0, 'session-one');
		$resource->init([], $this->context('chatbot-main'));
		$resource->createConversation('conversation-zero');
		$this->assertSame('user', $database->conversation['owner_type']);
		$this->assertSame('0', $database->conversation['owner_id']);
	}

	public function testEncryptedConversationAndMessagesAreReadableButNotPlaintextInDatabase(): void {
		$database = new ConversationDatabaseStub();
		$resource = $this->resource($database, 42, 'session-one', [
			'encryption' => [
				'method' => 'xchacha20poly1305-v1',
				'key_id' => 'key-2026',
				'keys' => ['key-2026' => str_repeat('s', 48)]
			]
		], true);
		$resource->init([], $this->context('chatbot-main'));
		$conversation = $resource->createConversation('conversation-one', 'Private title', openingMessage: 'Private opening');
		$resource->appendNodeHistory('assistant', ['id' => 'msg1', 'role' => 'user', 'content' => 'Private content', 'tag' => 'private metadata']);

		$this->assertSame('Private title', $conversation->getTitle());
		$this->assertSame('Private opening', $conversation->getOpeningMessage());
		$this->assertNotSame('Private title', $database->conversation['title']);
		$this->assertNotSame('Private opening', $database->conversation['opening_message']);
		$this->assertSame('xchacha20poly1305-v1', $database->conversation['encryption_method']);
		$this->assertNotSame('Private content', array_values($database->messages)[0]['content']);
		$this->assertNotSame('{"tag":"private metadata"}', array_values($database->messages)[0]['payload']);
		$this->assertSame('Private content', $resource->loadNodeHistory('assistant')[0]['content']);
		$this->assertSame('private metadata', $resource->loadNodeHistory('assistant')[0]['tag']);
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
				'payload' => $values[6],
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

