<?php declare(strict_types=1);

namespace MissionBay\Test\Profile;

use MissionBay\Context\AgentContext;
use MissionBay\Profile\NoOpProfileSelector;
use PHPUnit\Framework\TestCase;

final class NoOpProfileSelectorTest extends TestCase {

	public function testReturnsOneUnrestrictedDefaultPlan(): void {
		$plans = (new NoOpProfileSelector())->selectPlans('user', 'system', new AgentContext());
		$this->assertSame('noopprofileselector', NoOpProfileSelector::getName());
		$this->assertCount(1, $plans);
		$this->assertSame('default', $plans[0]->getProfileName());
		$this->assertSame([], $plans[0]->getRequiredTools());
		$this->assertNull($plans[0]->getAllowedTools());
	}

	public function testRepeatedSelectionDoesNotRetainPriorPlanState(): void {
		$selector = new NoOpProfileSelector();
		$first = $selector->selectPlans('first', '', new AgentContext());
		$second = $selector->selectPlans('second', '', new AgentContext());
		$this->assertFalse($first[0] === $second[0]);
	}
}
