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

namespace Base3\MissionBay\Test\Exception;

use Base3\MissionBay\Exception\AgentRunCancelledException;
use PHPUnit\Framework\TestCase;

final class AgentRunCancelledExceptionBehaviorTest extends TestCase {

	public function testDefaultCancellationReasonIsExplicit(): void {
		$exception = new AgentRunCancelledException();
		$this->assertSame('Agent run cancelled by user.', $exception->getMessage());
		$this->assertTrue($exception instanceof \RuntimeException);
	}

	public function testCallerCanSupplyMoreSpecificCancellationReason(): void {
		$exception = new AgentRunCancelledException('Stopped during tool phase.');
		$this->assertSame('Stopped during tool phase.', $exception->getMessage());
	}
}
