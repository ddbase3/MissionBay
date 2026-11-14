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

namespace Base3\MissionBay\Test\Memory;

use Base3\MissionBay\Memory\NoMemory;
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
