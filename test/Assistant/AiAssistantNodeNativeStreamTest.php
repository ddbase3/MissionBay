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

require_once __DIR__ . '/NativeStreamEventSink.php';
require_once __DIR__ . '/NativeStreamTurnService.php';
require_once __DIR__ . '/NativeStreamFinalResponseService.php';
require_once __DIR__ . '/NativeStreamMemoryService.php';
require_once __DIR__ . '/NativeStreamFallbackBuilder.php';
