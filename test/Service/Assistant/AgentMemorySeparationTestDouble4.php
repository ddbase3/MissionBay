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

final class AgentMemorySeparationTestDouble4 implements IAgentContextContributor  {
			public static function getName(): string {
				return 'testpurecontributor';
			}

			public function id(): string {
				return 'pure-context';
			}

			public function contribute(IAgentContext $context): iterable {
				return [new AgentInstructionBlock('pure', 'Pure context block')];
			}

			public function getPriority(): int {
				return 5;
			}
		}
