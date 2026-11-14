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

namespace Base3\MissionBay\Test\Service\Assistant;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentContextContributor;
use Base3\AssistantFoundation\Api\IAgentConversationMemory;
use Base3\AssistantFoundation\Api\IAgentMemory;
use Base3\AssistantFoundation\Dto\AgentConversation;
use Base3\AssistantFoundation\Dto\AgentConversationScope;
use Base3\AssistantFoundation\Dto\AgentInstructionBlock;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Resource\KnowledgeAgentResource;
use Base3\MissionBay\Service\Assistant\AgentAssistantContextContributionService;
use Base3\MissionBay\Service\Assistant\AgentAssistantMemoryService;
use Base3\MissionBay\Service\Assistant\AgentAssistantMessageFactory;
use Base3\MissionBay\Service\Assistant\AgentAssistantTurnService;
use Base3\MissionBay\Service\Memory\AgentMemoryRoleResolver;
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
