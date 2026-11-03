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

final class AgentMemorySeparationTestDouble2 implements IAgentMemory  {
			public int $loads = 0;
			public int $writes = 0;

			public static function getName(): string {
				return 'testgenericmemory';
			}

			public function loadNodeHistory(string $nodeId): array {
				$this->loads++;
				return [[
					'role' => 'assistant',
					'content' => 'Generic memory must not be used as conversation history.'
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
				return 30;
			}
		}
