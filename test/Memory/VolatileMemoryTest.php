<?php declare(strict_types=1);

namespace MissionBay\Test\Memory;

use MissionBay\Memory\VolatileMemory;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MissionBay\Memory\VolatileMemory
 */
class VolatileMemoryTest extends TestCase {

	public function testGetNameReturnsStableTechnicalName(): void {
		$this->assertSame('volatilememory', VolatileMemory::getName());
	}

	public function testLoadNodeHistoryReturnsEmptyArrayForUnknownNode(): void {
		$mem = new VolatileMemory();
		$this->assertSame([], $mem->loadNodeHistory('missing'));
	}

	public function testAppendNodeHistoryStoresMessagesPerNode(): void {
		$mem = new VolatileMemory();

		$mem->appendNodeHistory('n1', ['role' => 'user', 'content' => 'a']);
		$mem->appendNodeHistory('n1', ['role' => 'assistant', 'content' => 'b']);
		$mem->appendNodeHistory('n2', ['role' => 'user', 'content' => 'x']);

		$this->assertSame([
			['role' => 'user', 'content' => 'a'],
			['role' => 'assistant', 'content' => 'b'],
		], $mem->loadNodeHistory('n1'));

		$this->assertSame([
			['role' => 'user', 'content' => 'x'],
		], $mem->loadNodeHistory('n2'));
	}

	public function testAppendNodeHistoryEnforcesMaxLimitByKeepingLatestMessages(): void {
		$mem = new VolatileMemory();

		for ($i = 1; $i <= 25; $i++) {
			$mem->appendNodeHistory('n1', ['id' => (string)$i, 'n' => $i]);
		}

		$history = $mem->loadNodeHistory('n1');

		$this->assertCount(20, $history);

		// Should keep only last 20: 6..25
		$this->assertSame('6', $history[0]['id']);
		$this->assertSame(6, $history[0]['n']);

		$this->assertSame('25', $history[19]['id']);
		$this->assertSame(25, $history[19]['n']);
	}

	public function testSetFeedbackReturnsFalseWhenNodeDoesNotExist(): void {
		$mem = new VolatileMemory();
		$this->assertFalse($mem->setFeedback('missing', 'm1', 'up'));
	}

	public function testSetFeedbackReturnsFalseWhenMessageIdNotFound(): void {
		$mem = new VolatileMemory();

		$mem->appendNodeHistory('n1', ['id' => 'a', 'content' => 'x']);
		$this->assertFalse($mem->setFeedback('n1', 'missing', 'up'));
	}

	public function testSetFeedbackSetsFeedbackAndReturnsTrueWhenMessageFound(): void {
		$mem = new VolatileMemory();

		$mem->appendNodeHistory('n1', ['id' => 'm1', 'content' => 'x']);
		$mem->appendNodeHistory('n1', ['id' => 'm2', 'content' => 'y']);

		$this->assertTrue($mem->setFeedback('n1', 'm2', 'down'));

		$history = $mem->loadNodeHistory('n1');
		$this->assertSame('down', $history[1]['feedback']);

		// allow clearing feedback
		$this->assertTrue($mem->setFeedback('n1', 'm2', null));

		$history2 = $mem->loadNodeHistory('n1');
		$this->assertArrayHasKey('feedback', $history2[1]);
		$this->assertNull($history2[1]['feedback']);
	}

	public function testResetNodeHistoryClearsNodeHistory(): void {
		$mem = new VolatileMemory();

		$mem->appendNodeHistory('n1', ['id' => 'm1']);
		$this->assertCount(1, $mem->loadNodeHistory('n1'));

		$mem->resetNodeHistory('n1');
		$this->assertSame([], $mem->loadNodeHistory('n1'));
	}

	public function testGetPriorityReturnsZero(): void {
		$mem = new VolatileMemory();
		$this->assertSame(0, $mem->getPriority());
	}
}
