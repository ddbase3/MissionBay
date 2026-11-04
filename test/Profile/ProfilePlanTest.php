<?php declare(strict_types=1);

namespace MissionBay\Test\Profile;

use MissionBay\Profile\ProfilePlan;
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
