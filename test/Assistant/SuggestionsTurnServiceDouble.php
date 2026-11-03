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

final class SuggestionsTurnServiceDouble implements IAgentAssistantTurnService {

	private ?AgentAssistantTurnOptions $options = null;

	public function run(
		AgentAssistantTurnResources $resources,
		IAgentContext $context,
		AgentAssistantTurnOptions $options,
		?callable $eventCallback = null
	): AgentAssistantTurnResult {
		$this->options = $options;

		return new AgentAssistantTurnResult(
			messages: [],
			userMessage: ['role' => 'user', 'content' => $options->getPrompt()],
			memories: [],
			nodeId: $options->getNodeId(),
			assistantMessageId: $options->getAssistantMessageId(),
			memoryWriteEnabled: $options->isMemoryWriteEnabled(),
			completed: false,
			fallbackContent: 'Suggestions'
		);
	}

	public function getOptions(): ?AgentAssistantTurnOptions {
		return $this->options;
	}
}
