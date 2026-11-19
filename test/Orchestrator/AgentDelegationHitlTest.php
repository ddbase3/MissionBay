<?php declare(strict_types=1);

namespace Base3\MissionBay\Test\Orchestrator;

use Base3\MissionBay\Test\Orchestrator\Fixture\AgentDelegationHitlScenario;
use PHPUnit\Framework\TestCase;

final class AgentDelegationHitlTest extends TestCase {

	public function testNestedAgentSuspensionResumeAndDenial(): void {
		AgentDelegationHitlScenario::run();
		$this->addToAssertionCount(1);
	}
}
