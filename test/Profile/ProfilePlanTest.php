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

use Base3\MissionBay\Profile\ProfilePlan;
use PHPUnit\Framework\TestCase;

final class ProfilePlanTest extends TestCase {

	public function testDefaultsMeanUnrestrictedToolSelection(): void {
		$plan = new ProfilePlan('default');
		$this->assertSame('default', $plan->getProfileName());
		$this->assertNull($plan->getSystemAppend());
		$this->assertNull($plan->getAllowedTools());
		$this->assertSame([], $plan->getRequiredTools());
	}

	public function testExplicitEmptyAllowedListIsDifferentFromUnrestricted(): void {
		$plan = new ProfilePlan('restricted', 'only read', [], ['read', 'lookup']);
		$this->assertSame('restricted', $plan->getProfileName());
		$this->assertSame('only read', $plan->getSystemAppend());
		$this->assertSame([], $plan->getAllowedTools());
		$this->assertSame(['read', 'lookup'], $plan->getRequiredTools());
	}
}
