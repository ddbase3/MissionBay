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

final class AiAssistantNodeNativeStreamTest extends TestCase {

	public function testIncompleteNativeStreamIsNotPublishedAgainOrWrittenToMemory(): void {
		$memoryService = new NativeStreamMemoryService();
		$node = new AiAssistantNode(
			new NativeStreamTurnService(),
			new NativeStreamFinalResponseService(),
			$memoryService,
			new NativeStreamFallbackBuilder()
		);
		$eventSink = new NativeStreamEventSink();
		$turnResult = $this->turnResult();
		$method = new \ReflectionMethod(AiAssistantNode::class, 'handleIncompleteTurn');
		$method->setAccessible(true);

		$output = $method->invoke($node, new AgentContext(), $eventSink, $turnResult);

		$this->assertSame('Partial native response', $output['message']['content']);
		$this->assertSame('native_stream_interrupted', $output['warning']);
		$this->assertSame(0, $memoryService->getAppendCalls());
		$this->assertSame(['error', 'done'], array_map(
			static fn(AgentExecutionEvent $event): string => $event->getName(),
			$eventSink->getEvents()
		));
	}

	private function turnResult(): AgentAssistantTurnResult {
		$orchestrationResult = new AgentToolOrchestratorResult(
			messages: [
				['role' => 'system', 'content' => 'You are a helpful assistant.'],
				['role' => 'user', 'content' => 'Explain the current status.']
			],
			finalAssistantMessage: null,
			completed: false,
			iterations: 1,
			failureCode: 'native_stream_interrupted',
			failureMessage: 'Native model streaming was interrupted after visible output had already been delivered.',
			finalOutputContent: 'Partial native response',
			finalResponseMode: AgentToolOrchestratorResult::FINAL_RESPONSE_NONE,
			executionStatus: AgentExecutionStatus::FAILED,
			finalOutputDelivery: AgentToolOrchestratorResult::FINAL_OUTPUT_DELIVERY_STREAMED
		);

		return new AgentAssistantTurnResult(
			messages: $orchestrationResult->getMessages(),
			userMessage: ['role' => 'user', 'content' => 'Explain the current status.'],
			memories: [],
			nodeId: 'assistant',
			assistantMessageId: 'assistant-message',
			memoryWriteEnabled: true,
			orchestrationResult: $orchestrationResult,
			completed: false
		);
	}
}

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

final class NativeStreamTurnService implements IAgentAssistantTurnService {

	public function run(
		AgentAssistantTurnResources $resources,
		IAgentContext $context,
		AgentAssistantTurnOptions $options,
		?callable $eventCallback = null
	): AgentAssistantTurnResult {
		throw new \LogicException('The turn service is not used by this test.');
	}
}

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

	public function getAppendCalls(): int {
		return $this->appendCalls;
	}
}

final class NativeStreamFallbackBuilder implements IAgentAssistantFallbackBuilder {

	public function build(AgentToolOrchestratorResult $orchestrationResult): string {
		return 'fallback';
	}
}
