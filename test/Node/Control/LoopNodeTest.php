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

use Base3\MissionBay\Api\IAgentNode;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Node\Control\LoopNode;
use PHPUnit\Framework\TestCase;

final class LoopNodeTest extends TestCase {

	public function testOutOfRangeAndNonIntegerCountsAreRejected(): void {
		$node = new LoopNode('loop');
		foreach ([null, -1, 1001, 1.2, '2', false, []] as $count) {
			$this->assertStringContainsString('between 0 and 1000', $node->execute(['count' => $count], [], new AgentContext())['error']);
		}
	}

	public function testMissingNodeIsRejectedForValidCount(): void {
		$this->assertStringContainsString('must be a valid node', (new LoopNode())->execute(['count' => 0], [], new AgentContext())['error']);
	}

	public function testZeroIterationsDoNotInvokeInnerNode(): void {
		$inner = $this->createMock(IAgentNode::class);
		$inner->expects($this->never())->method('execute');
		$this->assertSame(['results' => []], (new LoopNode())->execute(['count' => 0, 'node' => $inner], [], new AgentContext()));
	}
}
