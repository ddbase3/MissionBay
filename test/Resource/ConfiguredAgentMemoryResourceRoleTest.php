<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use AssistantFoundation\Api\IAgentConversationMemory;
use AssistantFoundation\Api\IAgentMemory;
use AssistantFoundation\Dto\AgentConversation;
use AssistantFoundation\Dto\AgentConversationScope;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Context\AgentContext;
use MissionBay\Resource\ConfiguredAgentMemoryResource;
use PHPUnit\Framework\TestCase;

final class ConfiguredAgentMemoryResourceRoleTest extends TestCase {

	public function testConversationMemoryIsDelegated(): void {
		$underlying = new ConfiguredMemoryTestStub();
		$wrapper = new ConfiguredAgentMemoryResource($this->resolver(), 'configured-conversation');
		$wrapper->init(['memory' => [$underlying]], new AgentContext());

		$scope = new AgentConversationScope(str_repeat('a', 64), 'chatbot-main', 'conversation-1');
		$wrapper->bindConversationScope($scope);

		$this->assertSame($scope, $underlying->scope);
		$this->assertSame([['role' => 'user', 'content' => 'Stored']], $wrapper->loadNodeHistory('assistant'));
		$wrapper->appendNodeHistory('assistant', ['role' => 'assistant', 'content' => 'Reply']);
		$this->assertSame(1, $underlying->writes);
	}

	public function testReadAndWriteSwitchesRestrictConversationAccess(): void {
		$underlying = new ConfiguredMemoryTestStub();
		$wrapper = new ConfiguredAgentMemoryResource($this->resolver(), 'configured-restricted');
		$wrapper->setConfig(['priority' => 4, 'read_enabled' => false, 'write_enabled' => false]);
		$wrapper->init(['memory' => [$underlying]], new AgentContext());

		$this->assertSame(4, $wrapper->getPriority());
		$this->assertFalse($wrapper->isReadEnabled());
		$this->assertFalse($wrapper->isWriteEnabled());
		$this->assertSame([], $wrapper->loadNodeHistory('assistant'));
		$wrapper->appendNodeHistory('assistant', ['role' => 'assistant', 'content' => 'Skipped']);
		$this->assertSame(0, $underlying->writes);
		$this->expectException(\RuntimeException::class);
		$wrapper->createConversation();
	}

	public function testGenericAgentMemoryIsRejected(): void {
		$underlying = new class implements IAgentMemory {
			public static function getName(): string { return 'genericmemory'; }
			public function loadNodeHistory(string $nodeId): array { return []; }
			public function appendNodeHistory(string $nodeId, array $message): void {}
			public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool { return false; }
			public function resetNodeHistory(string $nodeId): void {}
			public function updateNodeHistoryMessageMetadata(string $nodeId, string $messageId, array $metadata): bool { return false; }
			public function getPriority(): int { return 100; }
		};

		$wrapper = new ConfiguredAgentMemoryResource($this->resolver(), 'configured-invalid');

		$this->expectException(\RuntimeException::class);
		$wrapper->init(['memory' => [$underlying]], new AgentContext());
	}

	private function resolver(): IAgentConfigValueResolver {
		return new class implements IAgentConfigValueResolver {
			public function resolveValue(array|string|int|float|bool|null $config): mixed { return $config; }
		};
	}
}

final class ConfiguredMemoryTestStub implements IAgentConversationMemory {

	public int $writes = 0;
	public ?AgentConversationScope $scope = null;

	public static function getName(): string { return 'configuredmemoryteststub'; }
	public function bindConversationScope(AgentConversationScope $scope): void { $this->scope = $scope; }
	public function listConversations(): array { return []; }
	public function getConversation(string $conversationId): ?AgentConversation { return null; }
	public function getActiveConversation(): ?AgentConversation { return null; }
	public function createConversation(?string $conversationId = null, string $title = '', string $titleSource = AgentConversation::TITLE_SOURCE_TEMPORARY, string $openingMessage = ''): AgentConversation { throw new \RuntimeException(); }
	public function activateConversation(string $conversationId): AgentConversation { throw new \RuntimeException(); }
	public function renameConversation(string $conversationId, string $title, string $titleSource = AgentConversation::TITLE_SOURCE_MANUAL): AgentConversation { throw new \RuntimeException(); }
	public function deleteConversation(string $conversationId): void {}
	public function touchConversation(string $conversationId): AgentConversation { throw new \RuntimeException(); }
	public function loadNodeHistory(string $nodeId): array { return [['role' => 'user', 'content' => 'Stored']]; }
	public function appendNodeHistory(string $nodeId, array $message): void { $this->writes++; }
	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool { return true; }
	public function resetNodeHistory(string $nodeId): void {}
	public function updateNodeHistoryMessageMetadata(string $nodeId, string $messageId, array $metadata): bool { return false; }
	public function getPriority(): int { return 15; }
}
