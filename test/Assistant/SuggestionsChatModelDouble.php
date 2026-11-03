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

final class SuggestionsChatModelDouble implements IAiChatModel {

	public function complete(array $messages, array $tools = []): AiChatResult {
		return new AiChatResult('Suggestions', [], new AiResultMetadata('chat'));
	}

	public function chat(array $messages): string {
		return 'Suggestions';
	}

	public function raw(array $messages, array $tools = []): mixed {
		return [];
	}

	public function streamResult(
		array $messages,
		array $tools,
		callable $onData,
		?callable $onMeta = null
	): AiChatResult {
		return $this->complete($messages, $tools);
	}

	public function stream(array $messages, array $tools, callable $onData, ?callable $onMeta = null): void {
		$onData('Suggestions');
	}

	public function setOptions(array $options): void {
	}

	public function getOptions(): array {
		return [];
	}
}
