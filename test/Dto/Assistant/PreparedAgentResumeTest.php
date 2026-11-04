<?php declare(strict_types=1);

namespace MissionBay\Test\Dto\Assistant;

use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentInteractionRequest;
use AssistantFoundation\Dto\AgentResume;
use AssistantFoundation\Dto\AgentSuspension;
use AssistantFoundation\Dto\AgentSuspensionClaim;
use MissionBay\Dto\Assistant\PreparedAgentResume;
use PHPUnit\Framework\TestCase;

final class PreparedAgentResumeTest extends TestCase {

	public function testMatchingResumeAndClaimRetainServerOwnedSuspension(): void {
		$suspension = $this->suspension();
		$claim = new AgentSuspensionClaim('secret-handle', 'lease-1', $suspension);
		$resume = new AgentResume('secret-handle', [], 'Yes, proceed');
		$prepared = new PreparedAgentResume($resume, $claim);
		$this->assertSame($resume, $prepared->getResume());
		$this->assertSame($claim, $prepared->getClaim());
		$this->assertSame($suspension, $prepared->getSuspension());
		$this->assertSame('secret-handle', $prepared->getResumeHandle());
	}

	public function testMismatchedHandleCannotCombineResponseWithDifferentSuspension(): void {
		$claim = new AgentSuspensionClaim('trusted-handle', 'lease-1', $this->suspension());
		$this->expectException(\InvalidArgumentException::class);
		new PreparedAgentResume(new AgentResume('other-handle'), $claim);
	}

	public function testHandleComparisonDoesNotIgnoreWhitespace(): void {
		$claim = new AgentSuspensionClaim('trusted-handle', 'lease-1', $this->suspension());
		$this->expectException(\InvalidArgumentException::class);
		new PreparedAgentResume(new AgentResume(' trusted-handle'), $claim);
	}

	private function suspension(): AgentSuspension {
		$action = new AgentAction('call-1', AgentAction::TYPE_TOOL_CALL, 'write');
		$request = new AgentInteractionRequest('request-1', AgentInteractionRequest::KIND_APPROVAL, $action, 'hash', 'Title', 'Message');
		return new AgentSuspension('suspension-1', AgentExecutionStatus::AWAITING_APPROVAL, [$request], [], '2026-01-01');
	}
}
