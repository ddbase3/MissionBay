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
			\Base3\AssistantFoundation\Dto\AgentConversation::TITLE_SOURCE_AUTOMATIC
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

	private function resource(ConversationDatabaseStub $database, mixed $userId, string $sessionId, array $config = [], bool $withSodium = false, ?IEncryptionProfileService $encryption = null): DatabaseMemoryAgentResource {
		$userManager = $this->createStub(IUsermanager::class);
		$user = $userId === null ? null : User::fromArray(['id' => $userId]);
		$userManager->method('getUser')->willReturn($user);
		$classmap = $this->createStub(IClassMap::class);
		if ($withSodium) {
			$classmap->method('getInstancesByInterface')->willReturn([new SodiumCrypt()]);
		}
		$translation = $this->createStub(\Base3\CoreFoundation\Translation\Api\ITranslation::class);
		$translation->method('translate')->willReturnCallback(
			static fn (string $set, string $section, string $key): string => $key
		);
		if ($encryption === null) {
			$encryption = $this->createStub(IEncryptionProfileService::class);
			$encryption->method('getWriteKey')->willReturn(null);
		}
		$resource = new DatabaseMemoryAgentResource(
			$database,
			new DatabaseMemoryConfigResolverStub(),
			$userManager,
			$classmap,
			$translation,
			$encryption,
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

	public function testChatbotChannelWithoutSourceCannotCreatePersistentConversation(): void {
		$database = new ConversationDatabaseStub();
		$resource = $this->resource($database, 42, 'session-one');
		$resource->init([], $this->context('chatbot:' . hash('sha256', "chatbot\0example")));

		$this->expectException(\RuntimeException::class);
		try {
			$resource->createConversation('conversation-without-source');
		}
		finally {
			$this->assertNull($database->conversation);
			$this->assertFalse($this->containsQuery($database->queries, 'INSERT INTO base3_missionbay_conversation '));
		}
	}

	public function testChatbotChannelWithOnlyOneSourceFieldIsRejected(): void {
		$database = new ConversationDatabaseStub();
		$resource = $this->resource($database, 42, 'session-one');
		$context = $this->context('chatbot:' . hash('sha256', "chatbot\0example"));
		$context->setVar('chatbot_config_group', 'chatbot');
		$resource->init([], $context);

		$this->expectException(\RuntimeException::class);
		try {
			$resource->createConversation('conversation-missing-name');
		}
		finally {
			$this->assertNull($database->conversation);
		}
	}

	public function testChatbotChannelWithSourceCanCreatePersistentConversation(): void {
		$database = new ConversationDatabaseStub();
		$resource = $this->resource($database, 42, 'session-one');
		$context = $this->context('chatbot:' . hash('sha256', "chatbot\0example"));
		$context->setVar('chatbot_config_group', 'chatbot');
		$context->setVar('chatbot_config_name', 'example');
		$resource->init([], $context);
		$resource->createConversation('conversation-with-source');

		$this->assertSame('chatbot', $database->conversation['chatbot_config_group']);
		$this->assertSame('example', $database->conversation['chatbot_config_name']);
	}

	public function testScheduledAgentChannelDoesNotRequireChatbotSettingsReference(): void {
		$database = new ConversationDatabaseStub();
		$resource = $this->resource($database, 42, 'session-one');
		$resource->init([], $this->context('scheduled-agent:reporting'));
		$resource->createConversation('scheduled-conversation');

		$this->assertSame('scheduled-agent:reporting', $database->conversation['channel_id']);
		$this->assertNull($database->conversation['chatbot_config_group']);
		$this->assertNull($database->conversation['chatbot_config_name']);
	}

	public function testHostDefinedNativeUserIdZeroIsNotMistakenForAnonymous(): void {
		$database = new ConversationDatabaseStub();
		$resource = $this->resource($database, 0, 'session-one');
		$resource->init([], $this->context('chatbot-main'));
		$resource->createConversation('conversation-zero');
		$this->assertSame('user', $database->conversation['owner_type']);
		$this->assertSame('0', $database->conversation['owner_id']);
	}

	public function testEncryptionConfigurationIsOwnedByKeyHarbor(): void {
		$database = new ConversationDatabaseStub();
		$resource = $this->resource($database, 42, 'session-one');
		$this->assertArrayNotHasKey('encryption', $resource->getSchema()['properties']);
		$this->assertSame([], $database->queries);
	}

	public function testManagedProfileEncryptsConversationAndMessages(): void {
		$database = new ConversationDatabaseStub();
		$service = $this->managedEncryption(['key-2026' => str_repeat('s', 48)], 'key-2026');
		$resource = $this->resource($database, 42, 'session-one', [], true, $service);
		$resource->init([], $this->context('chatbot-main'));
		$resource->createConversation('encrypted', 'Private title', openingMessage: 'Private opening');
		$resource->appendNodeHistory('assistant', [
			'id' => 'message-one', 'role' => 'user', 'content' => 'Private message', 'tag' => 'private metadata'
		]);

		$this->assertSame('xchacha20poly1305-v1', $database->conversation['encryption_method']);
		$this->assertSame('key-2026', $database->conversation['encryption_key_id']);
		$this->assertStringStartsWith('v1:', $database->conversation['title']);
		$this->assertStringStartsWith('v1:', array_values($database->messages)[0]['content']);
		$this->assertSame('Private message', $resource->loadNodeHistory('assistant')[0]['content']);
		$this->assertSame('private metadata', $resource->loadNodeHistory('assistant')[0]['tag']);
	}

	public function testDefaultUnassignedMemoryRemainsUnencrypted(): void {
		$database = new ConversationDatabaseStub();
		$resource = $this->resource($database, 42, 'session-one');
		$resource->init([], $this->context('chatbot-main'));
		$resource->createConversation('unencrypted', 'Normal title');

		$this->assertSame('none', $database->conversation['encryption_method']);
		$this->assertSame('Normal title', $database->conversation['title']);
	}

	public function testHistoricalKeyRemainsUsableAfterRemovingCurrentAssignment(): void {
		$database = new ConversationDatabaseStub();
		$keys = ['old-key' => str_repeat('o', 48)];
		$encrypted = $this->resource($database, 42, 'session-one', [], true, $this->managedEncryption($keys, 'old-key'));
		$encrypted->init([], $this->context('chatbot-main'));
		$encrypted->createConversation('historical', 'Historic private title', openingMessage: 'Historic greeting');
		$encrypted->appendNodeHistory('assistant', ['id' => 'msg1', 'role' => 'user', 'content' => 'Historic private content']);

		$readingService = $this->managedEncryption($keys);
		$reading = $this->resource($database, 42, 'session-one', [], true, $readingService);
		$reading->init([], $this->context('chatbot-main'));
		$this->assertSame('Historic private title', $reading->getConversation('historical')->getTitle());
		$this->assertSame('Historic private content', $reading->loadNodeHistory('assistant')[0]['content']);
	}

	public function testKeyRotationKeepsOldMessagesAndUsesNewWriteKey(): void {
		$database = new ConversationDatabaseStub();
		$keys = ['old-key' => str_repeat('o', 48), 'new-key' => str_repeat('n', 48)];
		$oldResource = $this->resource($database, 42, 'session-one', [], true, $this->managedEncryption($keys, 'old-key'));
		$oldResource->init([], $this->context('chatbot-main'));
		$oldResource->createConversation('rotation', 'Rotation test');
		$oldResource->appendNodeHistory('assistant', ['id' => 'old', 'role' => 'user', 'content' => 'Old content']);

		$newResource = $this->resource($database, 42, 'session-one', [], true, $this->managedEncryption($keys, 'new-key'));
		$newResource->init([], $this->context('chatbot-main'));
		$this->assertSame('Rotation test', $newResource->getConversation('rotation')->getTitle());
		$newResource->appendNodeHistory('assistant', ['id' => 'new', 'role' => 'assistant', 'content' => 'New content']);
		$this->assertSame(['Old content', 'New content'], array_column($newResource->loadNodeHistory('assistant'), 'content'));
		$this->assertSame(['old-key', 'new-key'], array_column($database->messages, 'encryption_key_id'));
	}

	public function testBrokenAssignedProfileCannotWritePlaintext(): void {
		$database = new ConversationDatabaseStub();
		$service = $this->createStub(IEncryptionProfileService::class);
		$service->method('getWriteKey')->willThrowException(new \RuntimeException('Encryption profile is missing.'));
		$resource = $this->resource($database, 42, 'session-one', [], false, $service);
		$resource->init([], $this->context('chatbot-main'));

		$this->expectException(\RuntimeException::class);
		try {
			$resource->createConversation('would-be-plaintext', 'Secret');
		} finally {
			$this->assertNull($database->conversation);
		}
	}

	public function testResourceSchemaHasNoLocalEncryptionSettings(): void {
		$resource = $this->resource(new ConversationDatabaseStub(), 42, 'session-one');
		$this->assertArrayNotHasKey('encryption', $resource->getSchema()['properties']);
	}

	/** @param array<string,string> $keys */
	private function managedEncryption(array $keys, ?string $activeKey = null): IEncryptionProfileService {
		$service = $this->createStub(IEncryptionProfileService::class);
		$service->method('getWriteKey')->willReturn(
			$activeKey === null ? null : new EncryptionKeyMaterial('xchacha20poly1305-v1', $activeKey, $keys[$activeKey])
		);
		$service->method('getReadKey')->willReturnCallback(
			static function(string $method, string $keyId) use ($keys): string {
				if ($method !== 'xchacha20poly1305-v1' || !isset($keys[$keyId])) {
					throw new \RuntimeException('Historical encryption key is unavailable.');
				}
				return $keys[$keyId];
			}
		);
		return $service;
	}

	private function context(string $channelId): AgentContext {
		$context = new AgentContext();
		$context->setVar('conversation_channel_id', $channelId);
		return $context;
	}
}

require_once __DIR__ . '/ConversationDatabaseStub.php';
require_once __DIR__ . '/DatabaseMemoryConfigResolverStub.php';
