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

namespace Base3\MissionBay\Test\Dto\Assistant;

use Base3\AssistantFoundation\Dto\AgentExecutionStatus;
use Base3\AssistantFoundation\Dto\AiResultMetadata;
use Base3\MissionBay\Dto\Assistant\AgentAssistantTurnResult;
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
