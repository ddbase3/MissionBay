<?php declare(strict_types=1);

namespace MissionBay\Test\Dto\Assistant;

use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AiResultMetadata;
use MissionBay\Dto\Assistant\AgentAssistantTurnResult;
use PHPUnit\Framework\TestCase;

final class AgentAssistantTurnResultTest extends TestCase {

	public function testCompletedTurnWithoutOrchestrationExposesNeutralDefaults(): void {
		$turn = new AgentAssistantTurnResult([['role' => 'user', 'content' => 'hi']], ['content' => 'hi'], [], 'assistant', 'message-1', true);
		$this->assertSame([['role' => 'user', 'content' => 'hi']], $turn->getMessages());
		$this->assertSame('hi', $turn->getUserMessage()['content']);
		$this->assertSame([], $turn->getMemories());
		$this->assertSame('assistant', $turn->getNodeId());
		$this->assertSame('message-1', $turn->getAssistantMessageId());
		$this->assertTrue($turn->shouldWriteMemory());
		$this->assertTrue($turn->isCompleted());
		$this->assertTrue($turn->canGenerateFinalResponse());
		$this->assertSame(AgentExecutionStatus::COMPLETED, $turn->getExecutionStatus());
		$this->assertNull($turn->getAgentResult());
		$this->assertSame([], $turn->getToolCalls());
		$this->assertSame([], $turn->getCapabilitySelections());
		$this->assertSame(0, $turn->getIterations());
		$this->assertSame('', $turn->getFinalOutputContent());
		$this->assertFalse($turn->isSuspended());
		$this->assertFalse($turn->isCancelled());
		$this->assertSame('', $turn->getFailureCode());
		$this->assertSame('', $turn->getFailureMessage());
	}

	public function testIncompleteTurnHasRunningStatusAndFallbackContent(): void {
		$turn = new AgentAssistantTurnResult([], [], [], 'node', 'message', false, completed: false, fallbackContent: 'Not finished');
		$this->assertFalse($turn->isCompleted());
		$this->assertFalse($turn->canGenerateFinalResponse());
		$this->assertFalse($turn->shouldWriteMemory());
		$this->assertSame('Not finished', $turn->getFallbackContent());
		$this->assertSame(AgentExecutionStatus::RUNNING, $turn->getExecutionStatus());
		$this->assertSame([], $turn->getInteractionRequests());
	}

	public function testModelResultMetadataIsRecordedInOrder(): void {
		$turn = new AgentAssistantTurnResult([], [], [], 'node', 'message', false);
		$turn->addModelResult(new AiResultMetadata('chat', 'provider', 'model', 'req-1'));
		$turn->addModelResult(new AiResultMetadata('chat', 'provider', 'model', 'req-2'));
		$this->assertCount(2, $turn->getModelResults());
		$this->assertSame('req-1', $turn->getModelResults()[0]['request_id']);
		$this->assertSame('req-2', $turn->getModelResults()[1]['request_id']);
	}
}
