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

namespace Base3\MissionBay\Test\Orchestrator\Service;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentResultVerification;
use Base3\AssistantFoundation\Dto\AgentToolResult;
use Base3\MissionBay\Orchestrator\Service\AgentResultVerificationService;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys as Keys;
use PHPUnit\Framework\TestCase;

final class AgentResultVerificationServiceTest extends TestCase {

	public function testEmptyResultsAreStructurallyValid(): void {
		$result = (new AgentResultVerificationService())->verify($this->context([]));
		$record = $result->getPatch()[Keys::RESULT_VERIFICATIONS][0];
		$this->assertTrue($record->isVerified());
		$this->assertSame(AgentResultVerificationService::VERIFIER, $record->getVerifier());
		$this->assertFalse($record->getMetadata()['action_correlation_checked']);
		$this->assertSame([], $record->getIssues());
	}

	public function testValidCurrentActionMatchesItsToolResultAndPreservesEarlierVerifications(): void {
		$prior = new AgentResultVerification(1, 'earlier', AgentResultVerification::VERDICT_VERIFIED, 'ok');
		$result = (new AgentResultVerificationService())->verify($this->context([
			Keys::ITERATION => 2,
			Keys::ACTIONS => [new AgentAction('c1', AgentAction::TYPE_TOOL_CALL, 'read', [], ['iteration' => 2])],
			Keys::TOOL_RESULTS => [AgentToolResult::success('c1', 'read', [], ['found' => true])],
			Keys::RESULT_VERIFICATIONS => [$prior]
		]));
		$this->assertArrayNotHasKey(Keys::FAILURE_CODE, $result->getPatch());
		$this->assertSame($prior, $result->getPatch()[Keys::RESULT_VERIFICATIONS][0]);
		$record = $result->getPatch()[Keys::RESULT_VERIFICATIONS][1];
		$this->assertTrue($record->isVerified());
		$this->assertSame(2, $record->getIteration());
		$this->assertTrue($record->getMetadata()['action_correlation_checked']);
	}

	public function testMalformedAndRepeatedToolResultsAreRejected(): void {
		$result = (new AgentResultVerificationService())->verify($this->context([
			Keys::TOOL_RESULTS => [
				'bad',
				AgentToolResult::success('', '', [], null),
				AgentToolResult::success('duplicate', 'read', [], null),
				AgentToolResult::failure('duplicate', 'read', [], '', '')
			]
		]));
		$this->assertSame('tool_result_verification_failed', $result->getPatch()[Keys::FAILURE_CODE]);
		$this->assertSame(Keys::PHASE_FAILED, $result->getPatch()[Keys::PHASE]);
		$issues = $result->getPatch()[Keys::RESULT_VERIFICATIONS][0]->getIssues();
		$codes = array_column($issues, 'code');
		foreach (['invalid_tool_result_type', 'missing_call_id', 'missing_tool_name', 'duplicate_call_id', 'missing_error_code', 'missing_error_message'] as $code) {
			$this->assertTrue(in_array($code, $codes, true), $code);
		}
	}

	public function testCurrentActionDuplicatesMissingResultsAndUnexpectedResultsAreReported(): void {
		$result = (new AgentResultVerificationService())->verify($this->context([
			Keys::ITERATION => 3,
			Keys::ACTIONS => [
				new AgentAction('a', AgentAction::TYPE_TOOL_CALL, 'read', [], ['iteration' => 3]),
				new AgentAction('a', AgentAction::TYPE_TOOL_CALL, 'read', [], ['iteration' => 3]),
				new AgentAction('', AgentAction::TYPE_TOOL_CALL, 'read', [], ['iteration' => 3]),
				new AgentAction('old', AgentAction::TYPE_TOOL_CALL, 'read', [], ['iteration' => 1])
			],
			Keys::TOOL_RESULTS => [AgentToolResult::success('unexpected', 'read', [], 1)]
		]));
		$codes = array_column($result->getPatch()[Keys::RESULT_VERIFICATIONS][0]->getIssues(), 'code');
		$this->assertSame(1, count(array_filter($codes, static fn(string $code): bool => $code === 'duplicate_action_id')));
		$this->assertTrue(in_array('missing_action_id', $codes, true));
		$this->assertTrue(in_array('missing_action_result', $codes, true));
		$this->assertTrue(in_array('unexpected_tool_result', $codes, true));
		$this->assertSame(3, $result->getPatch()[Keys::RESULT_VERIFICATIONS][0]->getMetadata()['current_action_count']);
	}

	public function testStaleAndInvalidActionsAreIgnoredInCorrelation(): void {
		$result = (new AgentResultVerificationService())->verify($this->context([
			Keys::ITERATION => 9,
			Keys::ACTIONS => [null, 'bad', new AgentAction('old', AgentAction::TYPE_TOOL_CALL, 'read', [], ['iteration' => 8])],
			Keys::TOOL_RESULTS => [AgentToolResult::success('new', 'read', [], 'ok')]
		]));
		$this->assertTrue($result->getPatch()[Keys::RESULT_VERIFICATIONS][0]->isVerified());
		$this->assertFalse($result->getPatch()[Keys::RESULT_VERIFICATIONS][0]->getMetadata()['action_correlation_checked']);
	}

	/** @param array<string,mixed> $values */
	private function context(array $values): IAgentContext {
		$context = $this->createStub(IAgentContext::class);
		$context->method('getVar')->willReturnCallback(static fn(string $key): mixed => $values[$key] ?? null);
		return $context;
	}
}
