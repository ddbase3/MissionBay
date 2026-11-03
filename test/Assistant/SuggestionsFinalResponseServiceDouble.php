<?php declare(strict_types=1);

namespace MissionBay\Test\Assistant;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AiResultMetadata;
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

final class SuggestionsFinalResponseServiceDouble implements IAgentAssistantFinalResponseService {

	public function createDirectResponse(IAiChatModel $model, AgentAssistantTurnResult $turnResult): string {
		return 'Suggestions';
	}

	public function createStreamingResponse(
		IAiChatModel $model,
		AgentAssistantTurnResult $turnResult,
		callable $onData,
		?callable $onMeta = null
	): string {
		return 'Suggestions';
	}

	public function createAssistantMessage(AgentAssistantTurnResult $turnResult, string $content): array {
		return [
			'id' => $turnResult->getAssistantMessageId(),
			'role' => 'assistant',
			'content' => $content
		];
	}
}
