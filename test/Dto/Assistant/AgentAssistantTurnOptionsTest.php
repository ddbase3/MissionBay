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

use Base3\AssistantFoundation\Dto\AgentBudget;
use Base3\AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use Base3\AssistantFoundation\Dto\AgentCapabilitySourceConfig;
use Base3\MissionBay\Dto\Assistant\AgentAssistantTurnOptions;
use Base3\MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use PHPUnit\Framework\TestCase;

final class AgentAssistantTurnOptionsTest extends TestCase {

	public function testStageIdsKeepConfiguredOrder(): void {
		$options = new AgentAssistantTurnOptions(
			prompt: 'test',
			stageIds: [
				'model-decision',
				'context-assessment',
				'tool-execution'
			]
		);

		$this->assertSame([
			'model-decision',
			'context-assessment',
			'tool-execution'
		], $options->getStageIds());
	}

	public function testStageIdsAreTrimmed(): void {
		$options = new AgentAssistantTurnOptions(
			prompt: 'test',
			stageIds: [' model-decision ', ' tool-execution ']
		);

		$this->assertSame([
			'model-decision',
			'tool-execution'
		], $options->getStageIds());
	}


	public function testDefaultToolLoopLimitIsTen(): void {
		$options = new AgentAssistantTurnOptions(prompt: 'test');

		$this->assertSame(10, $options->getMaxToolLoops());
	}

	public function testBudgetIsExposedAsRunState(): void {
		$budget = new AgentBudget(maxTotalTokens: 5000, maxToolCalls: 10);
		$options = new AgentAssistantTurnOptions(
			prompt: 'test',
			budget: $budget
		);

		$this->assertSame($budget, $options->getBudget());
	}

	public function testCapabilitySelectionConfigIsExposed(): void {
		$config = new AgentCapabilitySelectionConfig(maxTools: 12);
		$options = new AgentAssistantTurnOptions(
			prompt: 'test',
			capabilitySelectionConfig: $config
		);

		$this->assertSame($config, $options->getCapabilitySelectionConfig());
	}


	public function testModelDecisionConfigDefaultsToAiGuarded(): void {
		$options = new AgentAssistantTurnOptions(prompt: 'test');

		$this->assertSame(AgentModelDecisionConfig::STRATEGY_AI_GUARDED, $options->getModelDecisionConfig()->getStrategy());
		$this->assertTrue($options->getModelDecisionConfig()->isRepairEnabled());
	}

	public function testCapabilitySourceConfigIsExposed(): void {
		$config = AgentCapabilitySourceConfig::fromArray(['modules' => ['coding-style']]);
		$options = new AgentAssistantTurnOptions(
			prompt: 'test',
			capabilitySourceConfig: $config
		);

		$this->assertSame($config, $options->getCapabilitySourceConfig());
	}



	public function testDeliberatePlanningFlagIsExposed(): void {
		$options = new AgentAssistantTurnOptions(
			prompt: 'test',
			deliberatePlanningEnabled: true
		);

		$this->assertTrue($options->isDeliberatePlanningEnabled());
	}

	public function testDuplicateStageIdsAreRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Duplicate agent stage id: model-decision');

		new AgentAssistantTurnOptions(
			prompt: 'test',
			stageIds: ['model-decision', 'model-decision']
		);
	}
}
