<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator\Suspension;

use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentInteractionRequest;
use AssistantFoundation\Dto\AgentSuspension;
use AssistantFoundation\Dto\AgentSuspensionClaim;
use AssistantFoundation\Exception\AgentSuspensionRepositoryException;
use MissionBay\Orchestrator\Suspension\UnavailableAgentSuspensionRepository;
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
