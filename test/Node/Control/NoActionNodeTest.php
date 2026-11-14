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

namespace Base3\MissionBay\Test\Node\Control;

use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Node\Control\NoActionNode;
use PHPUnit\Framework\TestCase;

final class NoActionNodeTest extends TestCase {

	public function testNeverProducesOutputs(): void {
		$node = new NoActionNode();
		$this->assertSame([], $node->execute(['text' => 'ignored'], [], new AgentContext()));
		$this->assertSame([], $node->execute([], [], new AgentContext()));
	}

	public function testNodeHasOnlyInputPort(): void {
		$node = new NoActionNode();
		$this->assertSame('noactionnode', NoActionNode::getName());
		$this->assertSame('text', $node->getInputDefinitions()[0]->name);
		$this->assertSame([], $node->getOutputDefinitions());
	}
}
