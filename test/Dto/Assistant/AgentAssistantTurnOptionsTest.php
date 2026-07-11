<?php declare(strict_types=1);

namespace MissionBay\Test\Dto\Assistant;

use AssistantFoundation\Dto\AgentBudget;
use MissionBay\Dto\Assistant\AgentAssistantTurnOptions;
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


	public function testBudgetIsExposedAsRunState(): void {
		$budget = new AgentBudget(maxTotalTokens: 5000, maxToolCalls: 10);
		$options = new AgentAssistantTurnOptions(
			prompt: 'test',
			budget: $budget
		);

		$this->assertSame($budget, $options->getBudget());
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
