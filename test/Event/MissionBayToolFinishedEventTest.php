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

namespace Base3\MissionBay\Test\Event;

use Base3\MissionBay\Event\MissionBayToolFinishedEvent;
use PHPUnit\Framework\TestCase;

final class MissionBayToolFinishedEventTest extends TestCase {

	public function testResultAndExecutionMetadataSurviveUnchanged(): void {
		$result = ['items' => [1, false, null]];
		$event = new MissionBayToolFinishedEvent('n', 'c', 'lookup', 'Lookup', ['q' => 1], $result, 6, 'fixed', 2, ['ids' => ['x']]);
		$this->assertSame('n', $event->getNodeId());
		$this->assertSame('c', $event->getCallId());
		$this->assertSame('lookup', $event->getToolName());
		$this->assertSame('Lookup', $event->getLabel());
		$this->assertSame(['q' => 1], $event->getArguments());
		$this->assertSame($result, $event->getResult());
		$this->assertSame(6, $event->getIteration());
		$this->assertSame('fixed', $event->getTimestamp());
		$this->assertSame(2, $event->getCallIndex());
		$this->assertSame(['ids' => ['x']], $event->getTrace());
	}

	public function testNullResultAndDefaultTimestampAreSupported(): void {
		$event = new MissionBayToolFinishedEvent('n', 'c', 't', 'T', [], null, 0);
		$this->assertNull($event->getResult());
		$this->assertNotEmpty($event->getTimestamp());
	}
}
