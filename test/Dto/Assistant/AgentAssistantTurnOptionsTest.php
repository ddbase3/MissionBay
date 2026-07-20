<?php declare(strict_types=1);

namespace MissionBay\Test\Dto\Assistant;

use AssistantFoundation\Dto\AgentBudget;
use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use AssistantFoundation\Dto\AgentCapabilitySourceConfig;
use MissionBay\Dto\Assistant\AgentAssistantTurnOptions;
use MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
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
