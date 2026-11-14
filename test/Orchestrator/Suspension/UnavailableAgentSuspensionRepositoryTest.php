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

namespace Base3\MissionBay\Test\Orchestrator\Suspension;

use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentExecutionStatus;
use Base3\AssistantFoundation\Dto\AgentInteractionRequest;
use Base3\AssistantFoundation\Dto\AgentSuspension;
use Base3\AssistantFoundation\Dto\AgentSuspensionClaim;
use Base3\AssistantFoundation\Exception\AgentSuspensionRepositoryException;
use Base3\MissionBay\Orchestrator\Suspension\UnavailableAgentSuspensionRepository;
use PHPUnit\Framework\TestCase;

final class UnavailableAgentSuspensionRepositoryTest extends TestCase {

	public function testLookupAndClaimFailClosedWithoutPersistentBackend(): void {
		$repository = new UnavailableAgentSuspensionRepository();
		foreach ([
			static fn() => $repository->findPending('owner'),
			static fn() => $repository->findAll('owner'),
			static fn() => $repository->claim('resume-handle')
		] as $operation) {
			$this->assertUnavailable($operation);
		}
	}

	public function testCreateAndConsumeFailClosedWhileReleaseIsHarmless(): void {
		$repository = new UnavailableAgentSuspensionRepository();
		$suspension = $this->suspension();
		$claim = new AgentSuspensionClaim('handle', 'token', $suspension);
		$this->assertUnavailable(static fn() => $repository->create($suspension, 300));
		$this->assertUnavailable(static fn() => $repository->consume($claim));
		$repository->release($claim);
		$this->addToAssertionCount(1);
	}

	private function suspension(): AgentSuspension {
		$action = new AgentAction('action', AgentAction::TYPE_TOOL_CALL, 'write');
		$request = new AgentInteractionRequest('request', AgentInteractionRequest::KIND_APPROVAL, $action, 'fingerprint', 'Review', 'Approve?');
		return new AgentSuspension('suspension', AgentExecutionStatus::AWAITING_APPROVAL, [$request], [], '2026-01-01T00:00:00Z');
	}

	private function assertUnavailable(callable $operation): void {
		try {
			$operation();
			$this->fail('Operation must reject missing persistent suspension storage.');
		} catch (AgentSuspensionRepositoryException $e) {
			$this->assertSame(AgentSuspensionRepositoryException::REASON_UNAVAILABLE, $e->getReason());
			$this->assertStringContainsString('IStateStore', $e->getMessage());
		}
	}
}
