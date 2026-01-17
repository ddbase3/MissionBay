<?php declare(strict_types=1);

namespace MissionBay\Test\Memory;

use MissionBay\Memory\NoMemory;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MissionBay\Memory\NoMemory
 */
class NoMemoryTest extends TestCase {

	public function testGetNameReturnsStableTechnicalName(): void {
		$this->assertSame('nomemory', NoMemory::getName());
	}

	public function testLoadNodeHistoryAlwaysReturnsEmptyArray(): void {
		$mem = new NoMemory();

		$this->assertSame([], $mem->loadNodeHistory('n1'));
		$this->assertSame([], $mem->loadNodeHistory(''));
	}

	public function testAppendNodeHistoryIsNoOp(): void {
		$mem = new NoMemory();

		$mem->appendNodeHistory('n1', ['role' => 'user', 'content' => 'x']);
		$mem->appendNodeHistory('n2', []);

		$this->assertSame([], $mem->loadNodeHistory('n1'));
		$this->assertSame([], $mem->loadNodeHistory('n2'));

		$this->assertTrue(true);
	}

	public function testSetFeedbackAlwaysReturnsFalse(): void {
		$mem = new NoMemory();

		$this->assertFalse($mem->setFeedback('n1', 'm1', 'up'));
		$this->assertFalse($mem->setFeedback('n1', 'm1', null));
	}

	public function testResetNodeHistoryIsNoOp(): void {
		$mem = new NoMemory();

		$mem->resetNodeHistory('n1');
		$this->assertSame([], $mem->loadNodeHistory('n1'));
	}

	public function testGetPriorityReturnsZero(): void {
		$mem = new NoMemory();

		$this->assertSame(0, $mem->getPriority());
	}
}
