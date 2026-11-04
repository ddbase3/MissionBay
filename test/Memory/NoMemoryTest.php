<?php declare(strict_types=1);

namespace MissionBay\Test\Memory;

use MissionBay\Memory\NoMemory;
use PHPUnit\Framework\TestCase;

final class NoMemoryTest extends TestCase {

	public function testNoOpStorageNeverRetainsMessagesOrFeedback(): void {
		$memory = new NoMemory();
		$memory->appendNodeHistory('node', ['role' => 'user', 'content' => 'private']);
		$this->assertSame([], $memory->loadNodeHistory('node'));
		$this->assertFalse($memory->setFeedback('node', 'message-id', 'positive'));
		$memory->resetNodeHistory('node');
		$this->assertSame([], $memory->loadNodeHistory('node'));
	}

	public function testNameAndPriorityAreStable(): void {
		$memory = new NoMemory();
		$this->assertSame('nomemory', NoMemory::getName());
		$this->assertSame(0, $memory->getPriority());
		$this->assertSame([], $memory->loadNodeHistory('other'));
	}
}
