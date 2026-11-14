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
use Base3\MissionBay\Node\Control\ForEachNode;
use PHPUnit\Framework\TestCase;

final class ForEachNodeTest extends TestCase {

	public function testMissingOrNonArrayItemsAreRejected(): void {
		$node = new ForEachNode('foreach');
		foreach ([[], ['items' => null], ['items' => 'text'], ['items' => 17]] as $inputs) {
			$this->assertStringContainsString('must be an array', $node->execute($inputs, [], new AgentContext())['error']);
		}
	}

	public function testMissingInnerNodeIsRejectedEvenForEmptyList(): void {
		$result = (new ForEachNode())->execute(['items' => []], [], new AgentContext());
		$this->assertStringContainsString('instance of IAgentNode', $result['error']);
	}

	public function testEmptyCollectionReturnsEmptyResultsWithoutInvokingNode(): void {
		$node = $this->createMock(IAgentNode::class);
		$node->expects($this->never())->method('execute');
		$this->assertSame(['results' => []], (new ForEachNode())->execute(['items' => [], 'node' => $node], [], new AgentContext()));
	}
}
