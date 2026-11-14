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

namespace Base3\MissionBay\Test\Orchestrator;

use Base3\AssistantFoundation\Dto\AgentExecutionStatus;
use Base3\MissionBay\Orchestrator\AgentToolOrchestratorResult;
use PHPUnit\Framework\TestCase;

final class AgentToolOrchestratorResultTest extends TestCase {

	public function testDefaultResultExposesEmptyEvidenceAndNoFinalResponse(): void {
		$result = new AgentToolOrchestratorResult([], null, false, 0);
		$this->assertSame([], $result->getMessages());
		$this->assertNull($result->getFinalAssistantMessage());
		$this->assertFalse($result->isCompleted());
		$this->assertFalse($result->canGenerateFinalResponse());
		$this->assertFalse($result->hasFailure());
		$this->assertSame([], $result->getToolResults());
		$this->assertSame([], $result->getMutationToolNames());
		$this->assertSame(0, $result->getIterations());
		$this->assertSame(AgentExecutionStatus::RUNNING, $result->getExecutionStatus());
	}

	public function testCompletedFinalAnswerAndDeliveryAreReportedIndependently(): void {
		$messages = [['role' => 'user', 'content' => 'Hello']];
		$finalMessage = ['role' => 'assistant', 'content' => 'Hi'];
		$result = new AgentToolOrchestratorResult(
			messages: $messages,
			finalAssistantMessage: $finalMessage,
			completed: true,
			iterations: 2,
			finalOutputContent: 'Hi',
			finalResponseMode: AgentToolOrchestratorResult::FINAL_RESPONSE_COMPLETE,
			finalOutputDelivery: AgentToolOrchestratorResult::FINAL_OUTPUT_DELIVERY_STREAMED,
			executionStatus: AgentExecutionStatus::COMPLETED
		);
		$this->assertSame($messages, $result->getMessages());
		$this->assertSame($finalMessage, $result->getFinalAssistantMessage());
		$this->assertSame('Hi', $result->getFinalOutputContent());
		$this->assertTrue($result->isFinalOutputStreamed());
		$this->assertTrue($result->canGenerateFinalResponse());
		$this->assertFalse($result->isPartialFinalResponse());
	}

	public function testPartialResponseRequiresWorkMessagesAndNotSuspendedStatus(): void {
		$noMessages = new AgentToolOrchestratorResult([], null, false, 1, finalResponseMode: AgentToolOrchestratorResult::FINAL_RESPONSE_PARTIAL);
		$this->assertFalse($noMessages->canGenerateFinalResponse());
		$withMessages = new AgentToolOrchestratorResult([['role' => 'user', 'content' => 'hello']], null, false, 1, finalResponseMode: AgentToolOrchestratorResult::FINAL_RESPONSE_PARTIAL);
		$this->assertTrue($withMessages->canGenerateFinalResponse());
		$this->assertTrue($withMessages->isPartialFinalResponse());
	}

	public function testFailurePreventsCompletedFinalResponseAndPreservesDetails(): void {
		$result = new AgentToolOrchestratorResult(
			messages: [['role' => 'user', 'content' => 'x']],
			finalAssistantMessage: null,
			completed: true,
			iterations: 3,
			failureCode: 'model_error',
			failureMessage: 'Provider unavailable',
			failureDetail: ['retryable' => true],
			finalResponseMode: AgentToolOrchestratorResult::FINAL_RESPONSE_COMPLETE
		);
		$this->assertTrue($result->hasFailure());
		$this->assertFalse($result->canGenerateFinalResponse());
		$this->assertSame('model_error', $result->getFailureCode());
		$this->assertSame('Provider unavailable', $result->getFailureMessage());
		$this->assertSame(['retryable' => true], $result->getFailureDetail());
	}

	public function testMutationToolNamesAreTrimmedUniqueAndScalarOnly(): void {
		$result = new AgentToolOrchestratorResult([], null, true, 1, mutationToolNames: [' update ', 'update', '', null, [], 'remove', 3]);
		$this->assertSame(['update', 'remove', '3'], $result->getMutationToolNames());
	}

	public function testCancelledStatusNeverPermitsFinalResponse(): void {
		$result = new AgentToolOrchestratorResult(
			messages: [['role' => 'user', 'content' => 'x']],
			finalAssistantMessage: null,
			completed: true,
			iterations: 2,
			finalResponseMode: AgentToolOrchestratorResult::FINAL_RESPONSE_PARTIAL,
			executionStatus: AgentExecutionStatus::CANCELLED
		);
		$this->assertTrue($result->isCancelled());
		$this->assertFalse($result->isSuspended());
		$this->assertFalse($result->canGenerateFinalResponse());
	}

	public function testRejectsUnknownStatusAndFinalResponseEnums(): void {
		foreach ([
			['executionStatus' => 'unrecognized'],
			['finalResponseMode' => 'unknown'],
			['finalOutputDelivery' => 'unrecognized'],
			['toolResults' => [new \stdClass()]],
			['interactionRequests' => [['id' => 'request']]],
			['capabilitySelections' => ['capability']],
			['toolContractValidations' => ['validation']],
		] as $named) {
			try {
				new AgentToolOrchestratorResult(...array_merge(['messages' => [], 'finalAssistantMessage' => null, 'completed' => false, 'iterations' => 0], $named));
				$this->fail('Invalid result data must be rejected.');
			}
			catch (\InvalidArgumentException $exception) {
				$this->assertNotEmpty($exception->getMessage());
			}
		}
	}

	public function testSuspensionRequiresInteractionAndResumeHandle(): void {
		foreach ([AgentExecutionStatus::AWAITING_APPROVAL, AgentExecutionStatus::AWAITING_INPUT] as $status) {
			try {
				new AgentToolOrchestratorResult([], null, false, 0, executionStatus: $status);
				$this->fail('Suspension without interaction must fail.');
			}
			catch (\InvalidArgumentException $exception) {
				$this->assertStringContainsString('resume handle', $exception->getMessage());
			}
		}
	}
}
