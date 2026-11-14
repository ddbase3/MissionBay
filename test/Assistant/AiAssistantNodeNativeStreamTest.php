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
