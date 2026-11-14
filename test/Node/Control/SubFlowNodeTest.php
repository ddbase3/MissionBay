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

use Base3\MissionBay\Api\IAgentFlow;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Node\Control\SubFlowNode;
use PHPUnit\Framework\TestCase;

final class SubFlowNodeTest extends TestCase {

	public function testMissingOrInvalidFlowFailsWithoutExecutingAnything(): void {
		$node = new SubFlowNode('subflow');
		$this->assertStringContainsString('Missing or invalid flow', $node->execute([], [], new AgentContext())['error']);
		$this->assertStringContainsString('subflow', $node->execute(['flow' => new \stdClass()], [], new AgentContext())['error']);
	}

	public function testReturnsFirstArrayResultOfClonedFlow(): void {
		$flow = $this->createStub(IAgentFlow::class);
		$flow->method('run')->willReturn([null, 'skip', ['ok' => true], ['unused' => 1]]);
		$result = (new SubFlowNode())->execute(['flow' => $flow, 'input' => 7], [], new AgentContext());
		$this->assertSame(['ok' => true], $result);
	}

	public function testNoArrayOutputResultsInEmptyOutput(): void {
		$flow = $this->createStub(IAgentFlow::class);
		$flow->method('run')->willReturn([null, false, 'none']);
		$this->assertSame([], (new SubFlowNode())->execute(['flow' => $flow], [], new AgentContext()));
	}
}
