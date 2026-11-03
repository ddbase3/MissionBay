<?php declare(strict_types=1);

namespace MissionBay\Test\Profile;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Api\IAgentConversationMemory;
use AssistantFoundation\Dto\AgentConversation;
use AssistantFoundation\Dto\AgentConversationScope;
use AssistantFoundation\Dto\AgentInstructionBlock;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Profile\AgentContextProfileResolver;
use MissionBay\Profile\AgentMemoryProfileResolver;
use MissionBay\Resource\AbstractAgentResource;
use MissionBay\Resource\AgentContext\Text\StaticTextContextAgentResource;
use PHPUnit\Framework\TestCase;

final class AgentMemoryProfileResolverTestDouble3Double1 extends AbstractAgentResource implements IAgentConversationMemory {
						public static function getName(): string { return 'testconversationmemory'; }
						public function getDescription(): string { return 'Test conversation memory.'; }
						public function bindConversationScope(AgentConversationScope $scope): void {}
						public function listConversations(): array { return []; }
						public function getConversation(string $conversationId): ?AgentConversation { return null; }
						public function getActiveConversation(): ?AgentConversation { return null; }
						public function createConversation(?string $conversationId = null, string $title = '', string $titleSource = AgentConversation::TITLE_SOURCE_TEMPORARY, string $openingMessage = ''): AgentConversation { throw new \RuntimeException(); }
						public function activateConversation(string $conversationId): AgentConversation { throw new \RuntimeException(); }
						public function renameConversation(string $conversationId, string $title, string $titleSource = AgentConversation::TITLE_SOURCE_MANUAL): AgentConversation { throw new \RuntimeException(); }
						public function deleteConversation(string $conversationId): void {}
						public function deleteConversationsByOwner(string $ownerType, string $ownerId): void {}
						public function deleteConversationsByChannel(string $channelId): void {}
						public function touchConversation(string $conversationId): AgentConversation { throw new \RuntimeException(); }
						public function loadNodeHistory(string $nodeId): array { return []; }
						public function appendNodeHistory(string $nodeId, array $message): void {}
						public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool { return false; }
						public function resetNodeHistory(string $nodeId): void {}
						public function updateNodeHistoryMessageMetadata(string $nodeId, string $messageId, array $metadata): bool { return false; }
						public function getPriority(): int { return 80; }
					}
