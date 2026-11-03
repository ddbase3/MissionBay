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

final class ConfiguredMemoryTestStub implements IAgentConversationMemory {

	public int $writes = 0;
	public array $deletedOwners = [];
	public array $deletedChannels = [];
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
	public function deleteConversationsByOwner(string $ownerType, string $ownerId): void { $this->deletedOwners[] = [$ownerType, $ownerId]; }
	public function deleteConversationsByChannel(string $channelId): void { $this->deletedChannels[] = $channelId; }
	public function touchConversation(string $conversationId): AgentConversation { throw new \RuntimeException(); }
	public function loadNodeHistory(string $nodeId): array { return [['role' => 'user', 'content' => 'Stored']]; }
	public function appendNodeHistory(string $nodeId, array $message): void { $this->writes++; }
	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool { return true; }
	public function resetNodeHistory(string $nodeId): void {}
	public function updateNodeHistoryMessageMetadata(string $nodeId, string $messageId, array $metadata): bool { return false; }
	public function getPriority(): int { return 15; }
}
