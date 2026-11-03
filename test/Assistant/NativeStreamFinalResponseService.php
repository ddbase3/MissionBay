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

final class NativeStreamFinalResponseService implements IAgentAssistantFinalResponseService {

	public function createDirectResponse(IAiChatModel $model, AgentAssistantTurnResult $turnResult): string {
		throw new \LogicException('A second final response must not be created.');
	}

	public function createStreamingResponse(
		IAiChatModel $model,
		AgentAssistantTurnResult $turnResult,
		callable $onData,
		?callable $onMeta = null
	): string {
		throw new \LogicException('A second final response must not be streamed.');
	}

	public function createAssistantMessage(AgentAssistantTurnResult $turnResult, string $content): array {
		return [
			'id' => $turnResult->getAssistantMessageId(),
			'role' => 'assistant',
			'content' => $content
		];
	}
}
