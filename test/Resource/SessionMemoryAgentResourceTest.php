<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use AssistantFoundation\Dto\AgentConversation;
use Base3\Session\Api\ISession;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Context\AgentContext;
use MissionBay\Resource\SessionMemoryAgentResource;
use PHPUnit\Framework\TestCase;

final class SessionMemoryAgentResourceTest extends TestCase {

	public function testConversationRoundTripUsesOnlyScalarSessionChunks(): void {
		$session = new SessionMemorySessionStub('test-session');
		$first = $this->resource($session, 'preset-main');
		$first->init([], $this->context('chatbot-main', 'conversation-one'));
		$first->appendNodeHistory('assistant', ['id' => 'u1', 'role' => 'user', 'content' => 'First question']);
		$first->appendNodeHistory('assistant', ['id' => 'a1', 'role' => 'assistant', 'content' => 'First answer']);

		$second = $this->resource($session, 'preset-main');
		$second->init([], $this->context('chatbot-main', 'conversation-one'));

		$this->assertSame([
			['id' => 'u1', 'role' => 'user', 'content' => 'First question'],
			['id' => 'a1', 'role' => 'assistant', 'content' => 'First answer']
		], $second->loadNodeHistory('assistant'));
		foreach ($session->values as $value) {
			$this->assertTrue(is_scalar($value), 'Session memory must persist only scalar chunk values.');
		}
	}

	public function testMessageMetadataCanBeUpdatedWithoutChangingMessageContent(): void {
		$session = new SessionMemorySessionStub('test-session');
		$resource = $this->resource($session, 'preset-main');
		$resource->init([], $this->context('chatbot-main', 'conversation-one'));
		$resource->appendNodeHistory('assistant', [
			'id' => 'u1',
			'role' => 'user',
			'content' => 'Cancelled question'
		]);

		$this->assertTrue($resource->updateNodeHistoryMessageMetadata(
			'assistant',
			'u1',
			['status' => 'cancelled', 'content' => 'must not replace']
		));
		$this->assertSame([[
			'id' => 'u1',
			'role' => 'user',
			'content' => 'Cancelled question',
			'status' => 'cancelled'
		]], $resource->loadNodeHistory('assistant'));
	}

	public function testDifferentChannelsAndConversationsAreIsolated(): void {
		$session = new SessionMemorySessionStub('test-session');
		$main = $this->resource($session, 'preset-main');
		$main->init([], $this->context('chatbot-main', 'conversation-one'));
		$main->appendNodeHistory('assistant', ['id' => 'main-1', 'role' => 'user', 'content' => 'Main conversation']);

		$otherConversation = $this->resource($session, 'preset-main');
		$otherConversation->init([], $this->context('chatbot-main', 'conversation-two'));
		$this->assertSame([], $otherConversation->loadNodeHistory('assistant'));

		$otherChannel = $this->resource($session, 'preset-main');
		$otherChannel->init([], $this->context('chatbot-secondary', 'conversation-one'));
		$this->assertSame([], $otherChannel->loadNodeHistory('assistant'));

		$this->assertSame([[
			'id' => 'main-1',
			'role' => 'user',
			'content' => 'Main conversation'
		]], $main->loadNodeHistory('assistant'));
	}

	public function testSameNamespaceSharesConversationsAcrossResourceIds(): void {
		$session = new SessionMemorySessionStub('test-session');
		$first = $this->resource($session, 'preset-main');
		$first->init([], $this->context('chatbot-main', 'conversation-one'));
		$first->appendNodeHistory('assistant', ['id' => 'u1', 'role' => 'user', 'content' => 'Shared']);

		$second = $this->resource($session, 'preset-secondary');
		$second->init([], $this->context('chatbot-main', 'conversation-one'));

		$this->assertSame([[
			'id' => 'u1',
			'role' => 'user',
			'content' => 'Shared'
		]], $second->loadNodeHistory('assistant'));
	}

	public function testDifferentNamespacesRemainIsolated(): void {
		$session = new SessionMemorySessionStub('test-session');
		$first = $this->resource($session, 'preset-main', ['namespace' => 'chatbot-a']);
		$first->init([], $this->context('chatbot-main', 'conversation-one'));
		$first->appendNodeHistory('assistant', ['id' => 'u1', 'role' => 'user', 'content' => 'Only A']);

		$second = $this->resource($session, 'preset-main', ['namespace' => 'chatbot-b']);
		$second->init([], $this->context('chatbot-main', 'conversation-one'));

		$this->assertSame([], $second->loadNodeHistory('assistant'));
	}

	public function testConversationMetadataCanBeListedRenamedActivatedAndDeleted(): void {
		$session = new SessionMemorySessionStub('test-session');
		$resource = $this->resource($session, 'preset-main');
		$resource->init([], $this->context('chatbot-main'));

		$first = $resource->createConversation('conversation-one', 'Temporary', openingMessage: 'Hello');
		$second = $resource->createConversation('conversation-two', 'Second');
		$this->assertCount(2, $resource->listConversations());
		$this->assertSame($second->getId(), $resource->getActiveConversation()?->getId());
		$this->assertSame('Hello', $first->getOpeningMessage());

		$manual = $resource->renameConversation('conversation-one', 'Manual title');
		$this->assertSame(AgentConversation::TITLE_SOURCE_MANUAL, $manual->getTitleSource());
		$unchanged = $resource->renameConversation('conversation-one', 'Automatic title', AgentConversation::TITLE_SOURCE_AUTOMATIC);
		$this->assertSame('Manual title', $unchanged->getTitle());

		$resource->activateConversation($first->getId());
		$this->assertSame($first->getId(), $resource->getActiveConversation()?->getId());
		$resource->deleteConversation($first->getId());
		$this->assertNull($resource->getConversation($first->getId()));
		$this->assertSame($second->getId(), $resource->getActiveConversation()?->getId());
	}

	public function testInvalidRenameDoesNotPersistInvalidConversationState(): void {
		$session = new SessionMemorySessionStub('test-session');
		$resource = $this->resource($session, 'preset-main');
		$resource->init([], $this->context('chatbot-main'));
		$resource->createConversation('conversation-one', 'Valid title');

		try {
			$resource->renameConversation('conversation-one', 'Invalid title', 'invalid-source');
			$this->fail('Expected invalid title source error.');
		}
		catch (\InvalidArgumentException) {
			$conversation = $resource->getConversation('conversation-one');
			$this->assertSame('Valid title', $conversation?->getTitle());
			$this->assertSame(AgentConversation::TITLE_SOURCE_TEMPORARY, $conversation?->getTitleSource());
		}
	}

	public function testHistoryIsNotTrimmedByDefault(): void {
		$session = new SessionMemorySessionStub('test-session');
		$resource = $this->resource($session, 'preset-main', ['max' => 2]);
		$resource->init([], $this->context('chatbot-main', 'conversation-one'));
		$resource->appendNodeHistory('assistant', ['id' => '1']);
		$resource->appendNodeHistory('assistant', ['id' => '2']);
		$resource->appendNodeHistory('assistant', ['id' => '3']);

		$this->assertSame(['1', '2', '3'], array_column($resource->loadNodeHistory('assistant'), 'id'));
	}

	public function testHistoryCanBeTrimmedExplicitlyAndReset(): void {
		$session = new SessionMemorySessionStub('test-session');
		$resource = $this->resource($session, 'preset-main', ['max' => 2, 'trim' => true]);
		$resource->init([], $this->context('chatbot-main', 'conversation-one'));
		$resource->appendNodeHistory('assistant', ['id' => '1']);
		$resource->appendNodeHistory('assistant', ['id' => '2']);
		$resource->appendNodeHistory('assistant', ['id' => '3']);

		$this->assertSame(['2', '3'], array_column($resource->loadNodeHistory('assistant'), 'id'));
		$resource->resetNodeHistory('assistant');
		$this->assertSame([], $resource->loadNodeHistory('assistant'));
	}

	public function testMissingMessageIdIsGeneratedAndMessageShapeMatchesDatabaseMemory(): void {
		$session = new SessionMemorySessionStub('test-session');
		$resource = $this->resource($session, 'preset-main');
		$resource->init([], $this->context('chatbot-main', 'conversation-one'));
		$resource->appendNodeHistory('assistant', ['timestamp' => '2026-09-15T13:00:00+02:00']);

		$history = $resource->loadNodeHistory('assistant');
		$this->assertCount(1, $history);
		$this->assertMatchesRegularExpression('/^message-[a-f0-9]{40}$/', (string)$history[0]['id']);
		$this->assertSame('', $history[0]['role']);
		$this->assertSame('', $history[0]['content']);
		$this->assertSame('2026-09-15T13:00:00+02:00', $history[0]['timestamp']);
	}

	public function testDuplicateMessageIdsAreRejectedWithoutChangingHistory(): void {
		$session = new SessionMemorySessionStub('test-session');
		$resource = $this->resource($session, 'preset-main');
		$resource->init([], $this->context('chatbot-main', 'conversation-one'));
		$resource->appendNodeHistory('assistant', ['id' => 'u1', 'role' => 'user', 'content' => 'First']);

		try {
			$resource->appendNodeHistory('assistant', ['id' => 'u1', 'role' => 'user', 'content' => 'Duplicate']);
			$this->fail('Expected duplicate message id error.');
		}
		catch (\RuntimeException) {
			$this->assertSame([[
				'id' => 'u1',
				'role' => 'user',
				'content' => 'First'
			]], $resource->loadNodeHistory('assistant'));
		}
	}

	public function testMetadataAndResetDoNotChangeConversationUpdatedAt(): void {
		$session = new SessionMemorySessionStub('test-session');
		$resource = $this->resource($session, 'preset-main');
		$resource->init([], $this->context('chatbot-main', 'conversation-one'));
		$resource->appendNodeHistory('assistant', ['id' => 'u1', 'role' => 'user', 'content' => 'Question']);
		$updatedAt = $resource->getConversation('conversation-one')?->getUpdatedAt();

		usleep(1000);
		$this->assertTrue($resource->setFeedback('assistant', 'u1', 'good'));
		$this->assertSame($updatedAt, $resource->getConversation('conversation-one')?->getUpdatedAt());

		usleep(1000);
		$resource->resetNodeHistory('assistant');
		$this->assertSame($updatedAt, $resource->getConversation('conversation-one')?->getUpdatedAt());
	}

	public function testConversationCountIsNotArtificiallyLimited(): void {
		$session = new SessionMemorySessionStub('test-session');
		$resource = $this->resource($session, 'preset-main');
		$resource->init([], $this->context('chatbot-main'));

		for ($index = 0; $index < 55; $index++) {
			$resource->createConversation('conversation-' . $index);
		}

		$this->assertCount(55, $resource->listConversations());
	}

	public function testCorruptSessionStoreFailsExplicitly(): void {
		$session = new SessionMemorySessionStub('test-session');
		$resource = $this->resource($session, 'preset-main');
		$resource->init([], $this->context('chatbot-main'));
		$resource->createConversation('conversation-one');
		$session->set('base3_missionbay_conversation_memory_chunk_00000', 'not-base64!');

		$this->expectException(\RuntimeException::class);
		$resource->listConversations();
	}

	public function testSessionDestroyRemovesConversationStore(): void {
		$session = new SessionMemorySessionStub('test-session');
		$first = $this->resource($session, 'preset-main');
		$first->init([], $this->context('chatbot-main'));
		$first->createConversation('conversation-one');
		$this->assertCount(1, $first->listConversations());

		$session->destroy();
		$second = $this->resource($session, 'preset-main');
		$second->init([], $this->context('chatbot-main'));
		$this->assertSame([], $second->listConversations());
	}

	public function testSchemaUsesDatabaseCompatibleHistoryRetentionOptions(): void {
		$resource = $this->resource(new SessionMemorySessionStub('test-session'), 'preset-main');
		$properties = $resource->getSchema()['properties'] ?? [];

		$this->assertArrayHasKey('max', $properties);
		$this->assertArrayHasKey('trim', $properties);
		$this->assertFalse($properties['trim']['default'] ?? true);
		$this->assertArrayNotHasKey('max_conversations', $properties);
	}

	public function testConversationScopeRequiresStableChannelId(): void {
		$resource = $this->resource(new SessionMemorySessionStub('test-session'), 'preset-main');

		$this->expectException(\RuntimeException::class);
		$resource->init([], new AgentContext());
	}

	public function testConversationScopeRequiresStableSessionIdentity(): void {
		$resource = $this->resource(new SessionMemorySessionStub(''), 'preset-main');

		$this->expectException(\RuntimeException::class);
		$resource->init([], $this->context('chatbot-main'));
	}

	/** @param array<string,mixed> $config */
	private function resource(SessionMemorySessionStub $session, string $id, array $config = []): SessionMemoryAgentResource {
		$resource = new SessionMemoryAgentResource($session, new SessionMemoryConfigResolverStub(), $id);
		$resource->setConfig(array_merge(['namespace' => 'chatbot', 'max' => 4], $config));
		return $resource;
	}

	private function context(string $channelId, string $conversationId = ''): AgentContext {
		$context = new AgentContext();
		$context->setVar('conversation_channel_id', $channelId);
		if ($conversationId !== '') {
			$context->setVar('conversation_id', $conversationId);
		}
		return $context;
	}
}

final class SessionMemorySessionStub implements ISession {

	public array $values = [];
	private bool $started = false;

	public function __construct(private readonly string $id) {}
	public function started(): bool { return $this->started; }
	public function getId(): string { return $this->id; }
	public function start(): bool { $this->started = true; return true; }
	public function destroy(): bool { $this->started = false; $this->values = []; return true; }
	public function get(string $key, mixed $default = null): mixed { return $this->values[$key] ?? $default; }
	public function set(string $key, mixed $value): void { $this->values[$key] = $value; }
	public function has(string $key): bool { return array_key_exists($key, $this->values); }
	public function remove(string $key): void { unset($this->values[$key]); }
}

final class SessionMemoryConfigResolverStub implements IAgentConfigValueResolver {
	public function resolveValue(array|string|int|float|bool|null $config): mixed { return $config; }
}
