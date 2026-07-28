<?php declare(strict_types=1);

namespace MissionBay\Test\Listener;

use MissionBay\Event\MissionBayToolFailedEvent;
use MissionBay\Event\MissionBayToolFinishedEvent;
use MissionBay\Event\MissionBayToolStartedEvent;
use MissionBay\Listener\MissionBayToolEventDisplayListener;
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
