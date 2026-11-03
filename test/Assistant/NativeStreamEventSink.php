<?php declare(strict_types=1);

namespace MissionBay\Test\Assistant;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentEventSink;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentExecutionEvent;
use AssistantFoundation\Dto\AgentExecutionStatus;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentAssistantFallbackBuilder;
use MissionBay\Api\IAgentAssistantFinalResponseService;
use MissionBay\Api\IAgentAssistantMemoryService;
use MissionBay\Api\IAgentAssistantTurnService;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\Assistant\AgentAssistantTurnOptions;
use MissionBay\Dto\Assistant\AgentAssistantTurnResources;
use MissionBay\Dto\Assistant\AgentAssistantTurnResult;
use MissionBay\Node\Ai\AiAssistantNode;
use MissionBay\Orchestrator\AgentToolOrchestratorResult;
use PHPUnit\Framework\TestCase;

final class NativeStreamEventSink implements IAgentEventSink {

	/** @var array<int,AgentExecutionEvent> */
	private array $events = [];

	public function emit(AgentExecutionEvent $event): void {
		$this->events[] = $event;
	}

	public function isCancelled(): bool {
		return false;
	}

	/** @return array<int,AgentExecutionEvent> */
	public function getEvents(): array {
		return $this->events;
	}
}
