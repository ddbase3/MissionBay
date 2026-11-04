<?php declare(strict_types=1);

namespace MissionBay\Test\Event;

use MissionBay\Event\MissionBayToolFailedEvent;
use PHPUnit\Framework\TestCase;

final class MissionBayToolFailedEventTest extends TestCase {

	public function testFailureDetailsAndNumericCodeArePreserved(): void {
		$event = new MissionBayToolFailedEvent('n', 'c', 'write', 'Write', ['id' => 4], 'access denied', 'permission', 403, 2, 'fixed', 1, ['safe' => true]);
		$this->assertSame('n', $event->getNodeId());
		$this->assertSame('c', $event->getCallId());
		$this->assertSame('write', $event->getToolName());
		$this->assertSame('Write', $event->getLabel());
		$this->assertSame(['id' => 4], $event->getArguments());
		$this->assertSame('access denied', $event->getErrorMessage());
		$this->assertSame('permission', $event->getErrorType());
		$this->assertSame(403, $event->getErrorCode());
		$this->assertSame(2, $event->getIteration());
		$this->assertSame('fixed', $event->getTimestamp());
		$this->assertSame(1, $event->getCallIndex());
		$this->assertSame(['safe' => true], $event->getTrace());
	}

	public function testStringErrorCodeAndDefaultValuesAreRetained(): void {
		$event = new MissionBayToolFailedEvent('n', 'c', 't', 'T', [], 'broken', 'exception', 'E_CUSTOM', 0);
		$this->assertSame('E_CUSTOM', $event->getErrorCode());
		$this->assertNotEmpty($event->getTimestamp());
		$this->assertSame(0, $event->getCallIndex());
		$this->assertSame([], $event->getTrace());
	}
}
