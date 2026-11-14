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

use Base3\AssistantFoundation\Dto\AgentToolResult;
use Base3\MissionBay\Dto\Assistant\AgentAssistantTurnResult;
use Base3\MissionBay\Dto\Assistant\AgentExecutionLedger;
use PHPUnit\Framework\TestCase;

final class AgentExecutionLedgerTest extends TestCase {

	public function testEmptyLedgerHasNoMutationAndNoGuard(): void {
		$ledger = AgentExecutionLedger::fromEvidence([], [], []);
		$this->assertFalse($ledger->hasMutationIntent());
		$this->assertFalse($ledger->requiresFinalResponseGuard());
		$this->assertFalse($ledger->requiresBufferedStreaming());
		$this->assertFalse($ledger->hasSuccessfulMutation());
		$this->assertSame('', $ledger->buildFinalResponseInstruction());
		$this->assertSame([], $ledger->getSuccessfulMutationCalls());
	}

	public function testSuccessfulFailedAndCachedMutationsAreDistinguished(): void {
		$ledger = AgentExecutionLedger::fromEvidence(
			[' write ', 'write', 'remove', '', [], null],
			[
				AgentToolResult::success('s1', 'write', ['id' => 2], ['changed' => true]),
				AgentToolResult::success('c1', 'remove', ['id' => 3], true, ['cache' => ['hit' => true]]),
				AgentToolResult::failure('f1', 'write', ['id' => 4], 'failed', 'access denied'),
				AgentToolResult::success('ignored', 'read', [], 'ok'),
				'not-a-result'
			],
			[['intent' => 'read'], ['intent' => 'mutation', 'mutation_intent' => true]]
		);
		$this->assertSame(['write', 'remove'], $ledger->getMutationToolNames());
		$this->assertTrue($ledger->hasSuccessfulMutation());
		$this->assertTrue($ledger->hasMutationIntent());
		$this->assertCount(1, $ledger->getSuccessfulMutationCalls());
		$this->assertCount(1, $ledger->getCachedMutationCalls());
		$this->assertCount(1, $ledger->getFailedMutationCalls());
		$this->assertSame('failed', $ledger->getFailedMutationCalls()[0]['error_code']);
		$this->assertSame('mutation', $ledger->getLatestDecisionAssessment()['intent']);
		$this->assertStringContainsString('successful_mutation_calls', $ledger->buildFinalResponseInstruction());
		$this->assertStringContainsString('At least one mutation tool completed successfully', $ledger->buildFinalResponseInstruction());
	}

	public function testCacheHitIsNeverCountedAsSuccessfulMutation(): void {
		$ledger = AgentExecutionLedger::fromEvidence(['delete'], [AgentToolResult::success('cached', 'delete', [], ['removed' => true], ['cache' => ['hit' => true]])], []);
		$this->assertFalse($ledger->hasSuccessfulMutation());
		$this->assertTrue($ledger->hasMutationIntent());
		$this->assertTrue($ledger->requiresBufferedStreaming());
		$this->assertStringContainsString('No mutation tool completed successfully', $ledger->buildFinalResponseInstruction());
	}

	public function testModelAssessmentMutationIntentActivatesGuardWithoutToolCall(): void {
		$ledger = AgentExecutionLedger::fromEvidence([], [], ['invalid', ['intent' => ' MUTATION ', 'mutation_intent' => false]]);
		$this->assertTrue($ledger->hasMutationIntent());
		$this->assertCount(1, $ledger->getModelDecisionAssessments());
		$this->assertTrue($ledger->requiresFinalResponseGuard());
		$this->assertSame([], $ledger->getSuccessfulMutationCalls());
		$this->assertStringContainsString('requested change was not performed', $ledger->buildFinalResponseInstruction());
	}

	public function testTurnWithoutOrchestrationHasEmptyLedger(): void {
		$turn = new AgentAssistantTurnResult([], [], [], 'node', 'message', false);
		$ledger = AgentExecutionLedger::fromTurnResult($turn);
		$this->assertFalse($ledger->hasMutationIntent());
		$this->assertSame([], $ledger->getMutationToolNames());
		$this->assertStringContainsString('nicht durchgeführt', $ledger->getSafeFallbackResponse());
	}
}
