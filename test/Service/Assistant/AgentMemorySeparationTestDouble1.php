<?php declare(strict_types=1);

namespace MissionBay\Test\Service\Assistant;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Api\IAgentConversationMemory;
use AssistantFoundation\Api\IAgentMemory;
use AssistantFoundation\Dto\AgentConversation;
use AssistantFoundation\Dto\AgentConversationScope;
use AssistantFoundation\Dto\AgentInstructionBlock;
use MissionBay\Context\AgentContext;
use MissionBay\Resource\KnowledgeAgentResource;
use MissionBay\Service\Assistant\AgentAssistantContextContributionService;
use MissionBay\Service\Assistant\AgentAssistantMemoryService;
use MissionBay\Service\Assistant\AgentAssistantMessageFactory;
use MissionBay\Service\Assistant\AgentAssistantTurnService;
use MissionBay\Service\Memory\AgentMemoryRoleResolver;
use PHPUnit\Framework\TestCase;

final class AgentMemorySeparationTestDouble1 implements IAgentConversationMemory  {
			public int $loads = 0;
			public int $writes = 0;

			public static function getName(): string {
				return 'testconversationmemory';
			}

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

			public function loadNodeHistory(string $nodeId): array {
				$this->loads++;
				return [[
					'role' => 'user',
					'content' => 'Stored conversation'
				]];
			}

			public function appendNodeHistory(string $nodeId, array $message): void {
				$this->writes++;
			}

			public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
				return false;
			}
			public function updateNodeHistoryMessageMetadata(string $nodeId, string $messageId, array $metadata): bool { return false; }

			public function resetNodeHistory(string $nodeId): void {
			}

			public function getPriority(): int {
				return 20;
			}
		}
