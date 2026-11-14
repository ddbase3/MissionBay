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
