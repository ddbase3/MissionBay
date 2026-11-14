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

namespace Base3\MissionBay\Test\Orchestrator\Stage;

use Base3\AssistantFoundation\Api\IAgentCapabilitySelector;
use Base3\AssistantFoundation\Api\IAgentStage;
use Base3\MissionBay\Orchestrator\Stage\AgentCapabilitySelectionStage;
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
