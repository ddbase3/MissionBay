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

final class AiAssistantNodeSuggestionsModeTest extends TestCase {

	public function testSuggestionsReadMemoryWithoutToolsOrMemoryWrites(): void {
		$turnService = new SuggestionsTurnServiceDouble();
		$memoryService = new SuggestionsMemoryServiceDouble();
		$node = new AiAssistantNode(
			$turnService,
			new SuggestionsFinalResponseServiceDouble(),
			$memoryService,
			new SuggestionsFallbackBuilderDouble(),
			'assistant'
		);

		$output = $node->execute(
			[
				'prompt' => 'Generate suggestions.',
				'mode' => 'suggestions'
			],
			['chatmodel' => [new SuggestionsChatModelDouble()]],
			new AgentContext()
		);

		$options = $turnService->getOptions();
		$this->assertNotNull($options);
		$this->assertSame('suggestions', $options->getMode());
		$this->assertFalse($options->areToolsEnabled());
		$this->assertTrue($options->isMemoryReadEnabled());
		$this->assertFalse($options->isMemoryWriteEnabled());
		$this->assertSame(0, $memoryService->getAppendCalls());
		$this->assertSame('Suggestions', $output['message']['content'] ?? null);
	}
}

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

final class SuggestionsMemoryServiceDouble implements IAgentAssistantMemoryService {

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
		callable $onMeta = null
	): AiChatResult {
		return $this->complete($messages, $tools);
	}

	public function stream(array $messages, array $tools, callable $onData, callable $onMeta = null): void {
		$onData('Suggestions');
	}

	public function setOptions(array $options): void {
	}

	public function getOptions(): array {
		return [];
	}
}

final class SuggestionsFallbackBuilderDouble implements IAgentAssistantFallbackBuilder {

	public function build(AgentToolOrchestratorResult $orchestrationResult): string {
		return 'Suggestions';
	}
}
