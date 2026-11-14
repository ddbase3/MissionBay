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

namespace Base3\MissionBay\Test\Listener;

use Base3\MissionBay\Event\MissionBayToolFailedEvent;
use Base3\MissionBay\Event\MissionBayToolFinishedEvent;
use Base3\MissionBay\Event\MissionBayToolStartedEvent;
use Base3\MissionBay\Listener\MissionBayToolEventDisplayListener;
use PHPUnit\Framework\TestCase;

final class MissionBayToolEventDisplayListenerTimestampTest extends TestCase {

	public function testDatabaseTimestampIsNormalizedToUtc(): void {
		$reflection = new \ReflectionClass(MissionBayToolEventDisplayListener::class);
		$listener = $reflection->newInstanceWithoutConstructor();
		$method = $reflection->getMethod('normalizeTimestamp');

		$this->assertSame(
			'2026-07-28 11:35:24',
			$method->invoke($listener, '2026-07-28T13:35:24+02:00')
		);
	}

	public function testToolEventsCreateUtcTimestampsByDefault(): void {
		$events = [
			new MissionBayToolStartedEvent('node', 'call', 'tool', 'Tool', [], 1),
			new MissionBayToolFinishedEvent('node', 'call', 'tool', 'Tool', [], [], 1),
			new MissionBayToolFailedEvent('node', 'call', 'tool', 'Tool', [], 'failed', 'runtime', 0, 1)
		];

		foreach($events as $event) {
			$this->assertMatchesRegularExpression('/\+00:00$/', $event->getTimestamp());
		}
	}
}
