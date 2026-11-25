<?php declare(strict_types=1);

namespace Base3\MissionBay\Test\Orchestrator;

use Base3\MissionBay\Test\Orchestrator\Fixture\ProgramToolLoopScenario;
use PHPUnit\Framework\TestCase;

final class ProgramToolLoopTest extends TestCase {

	public function testProgramLoopAndApprovalContinuations(): void {
		ProgramToolLoopScenario::run();
		$this->addToAssertionCount(1);
	}
}
