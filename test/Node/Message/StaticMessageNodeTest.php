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

namespace Base3\MissionBay\Test\Node\Message;

use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Node\Message\StaticMessageNode;
use PHPUnit\Framework\TestCase;

final class StaticMessageNodeTest extends TestCase {

	public function testOutputsTextAndDefaultsToEmptyString(): void {
		$node = new StaticMessageNode();
		$this->assertSame(['message' => 'hello'], $node->execute(['text' => 'hello'], [], new AgentContext()));
		$this->assertSame(['message' => ''], $node->execute([], [], new AgentContext()));
	}

	public function testScalarInputIsStringified(): void {
		$node = new StaticMessageNode();
		$this->assertSame(['message' => '0'], $node->execute(['text' => 0], [], new AgentContext()));
		$this->assertSame(['message' => '1'], $node->execute(['text' => true], [], new AgentContext()));
	}
}
