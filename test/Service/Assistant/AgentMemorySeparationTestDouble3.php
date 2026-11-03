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

final class AgentMemorySeparationTestDouble3 implements IAgentMemory, IAgentContextContributor  {
			public int $historyLoads = 0;
			public int $historyWrites = 0;
			public int $contributions = 0;

			public static function getName(): string {
				return 'testcontextcontributor';
			}

			public function id(): string {
				return 'context-primary';
			}

			public function contribute(IAgentContext $context): iterable {
				$this->contributions++;
				return [new AgentInstructionBlock(
					id: 'preferences',
					content: 'Prefer concise responses.',
					priority: 5,
					source: $this->id()
				)];
			}

			public function loadNodeHistory(string $nodeId): array {
				$this->historyLoads++;
				return [[
					'role' => 'system',
					'content' => 'Context contributor history must not be used.'
				]];
			}

			public function appendNodeHistory(string $nodeId, array $message): void {
				$this->historyWrites++;
			}

			public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
				return false;
			}
			public function updateNodeHistoryMessageMetadata(string $nodeId, string $messageId, array $metadata): bool { return false; }

			public function resetNodeHistory(string $nodeId): void {
			}

			public function getPriority(): int {
				return 10;
			}
		}
