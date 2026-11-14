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

namespace Base3\MissionBay\Test\Service\Assistant;

use Base3\MissionBay\Service\Assistant\AgentAssistantMessageFactory;
use PHPUnit\Framework\TestCase;

final class AgentAssistantMessageFactoryTest extends TestCase {

	public function testCancelledMessagesAreNotReusedAsAgentHistory(): void {
		$factory = new AgentAssistantMessageFactory();

		$this->assertFalse($factory->isVisibleHistoryEntry([
			'id' => 'user-one',
			'role' => 'user',
			'content' => 'Old cancelled request',
			'status' => 'cancelled'
		]));
		$this->assertTrue($factory->isVisibleHistoryEntry([
			'id' => 'user-two',
			'role' => 'user',
			'content' => 'Current request'
		]));
	}
}
