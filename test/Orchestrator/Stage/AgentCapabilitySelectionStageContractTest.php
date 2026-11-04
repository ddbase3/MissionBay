<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator\Stage;

use AssistantFoundation\Api\IAgentCapabilitySelector;
use AssistantFoundation\Api\IAgentStage;
use MissionBay\Orchestrator\Stage\AgentCapabilitySelectionStage;
use PHPUnit\Framework\TestCase;

final class AgentCapabilitySelectionStageContractTest extends TestCase {

	public function testDefaultStageHasStableNonAiIdentity(): void {
		$stage = new AgentCapabilitySelectionStage($this->createStub(IAgentCapabilitySelector::class));
		$this->assertSame('agentcapabilityselectionstage', AgentCapabilitySelectionStage::getName());
		$this->assertSame('capability-selection', $stage->id());
		$this->assertSame('capability-selection', $stage->name());
		$this->assertSame(IAgentStage::AI_USAGE_NONE, $stage->getAiUsage());
	}

	public function testCustomStageIdentityIsNotLost(): void {
		$stage = new AgentCapabilitySelectionStage($this->createStub(IAgentCapabilitySelector::class), 'custom-id', 'custom-stage', 1200);
		$this->assertSame('custom-id', $stage->id());
		$this->assertSame('custom-stage', $stage->name());
	}

	public function testBlankIdIsRejectedBeforeProcessing(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('stage id must not be empty');
		new AgentCapabilitySelectionStage($this->createStub(IAgentCapabilitySelector::class), '  ');
	}

	public function testBlankStageNameIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('stage name must not be empty');
		new AgentCapabilitySelectionStage($this->createStub(IAgentCapabilitySelector::class), 'valid-id', ' ');
	}

	public function testTooSmallContextLimitIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('at least 1000 characters');
		new AgentCapabilitySelectionStage($this->createStub(IAgentCapabilitySelector::class), 'valid-id', 'valid-stage', 999);
	}
}
