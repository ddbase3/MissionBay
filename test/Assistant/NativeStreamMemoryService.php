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

namespace Base3\MissionBay\Test\Assistant;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentEventSink;
use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Dto\AgentExecutionEvent;
use Base3\AssistantFoundation\Dto\AgentExecutionStatus;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MissionBay\Api\IAgentAssistantFallbackBuilder;
use Base3\MissionBay\Api\IAgentAssistantFinalResponseService;
use Base3\MissionBay\Api\IAgentAssistantMemoryService;
use Base3\MissionBay\Api\IAgentAssistantTurnService;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Dto\Assistant\AgentAssistantTurnOptions;
use Base3\MissionBay\Dto\Assistant\AgentAssistantTurnResources;
use Base3\MissionBay\Dto\Assistant\AgentAssistantTurnResult;
use Base3\MissionBay\Node\Ai\AiAssistantNode;
use Base3\MissionBay\Orchestrator\AgentToolOrchestratorResult;
use PHPUnit\Framework\TestCase;

final class NativeStreamMemoryService implements IAgentAssistantMemoryService {

	private int $appendCalls = 0;

	public function sortMemories(array $memories): array {
		return $memories;
	}

	public function buildInitialMessages(string $system, array $memories, string $nodeId, ?ILogger $logger = null): array {
		return [];
	}

	public function appendVisibleMessage(array $memories, string $nodeId, array $message, ?ILogger $logger = null): void {
		$this->appendCalls++;
	}

	public function updateVisibleMessageMetadata(
		array $memories,
		string $nodeId,
		string $messageId,
		array $metadata,
		?ILogger $logger = null
	): bool {
		return false;
	}

	public function getAppendCalls(): int {
		return $this->appendCalls;
	}
}
