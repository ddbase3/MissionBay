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

use Base3\MissionBay\Event\MissionBayToolStartedEvent;
use PHPUnit\Framework\TestCase;

final class MissionBayToolStartedEventTest extends TestCase {

	public function testExplicitTimestampCallIndexAndTraceArePreserved(): void {
		$event = new MissionBayToolStartedEvent('node', 'call', 'search', 'Search', ['query' => 'a'], 3, '2025-01-02T03:04:05+00:00', 5, ['step' => 4]);
		$this->assertSame('node', $event->getNodeId());
		$this->assertSame('call', $event->getCallId());
		$this->assertSame('search', $event->getToolName());
		$this->assertSame('Search', $event->getLabel());
		$this->assertSame(['query' => 'a'], $event->getArguments());
		$this->assertSame(3, $event->getIteration());
		$this->assertSame(5, $event->getCallIndex());
		$this->assertSame(['step' => 4], $event->getTrace());
		$this->assertSame('2025-01-02T03:04:05+00:00', $event->getTimestamp());
	}

	public function testTimestampIsGeneratedIfOmitted(): void {
		$event = new MissionBayToolStartedEvent('n', 'c', 't', 'T', [], 0);
		$this->assertNotEmpty($event->getTimestamp());
		$this->assertSame(0, $event->getCallIndex());
		$this->assertSame([], $event->getTrace());
	}
}
