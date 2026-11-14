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

namespace Base3\MissionBay\Test\Profile;

use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Profile\NoOpProfileSelector;
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
