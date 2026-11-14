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

namespace Base3\MissionBay\Test\Content;

use Base3\CoreFoundation\Api\IClassMap;
use Base3\MissionBay\Api\IAgentNode;
use Base3\MissionBay\Content\AgentNodes;
use Base3\MissionBay\Node\Control\IfNode;
use PHPUnit\Framework\TestCase;

final class AgentNodesDiscoveryBehaviorTest extends TestCase {

	public function testNonJsonOutputDoesNotQueryClassMap(): void {
		$map = $this->createMock(IClassMap::class);
		$map->expects($this->never())->method('getInstancesByInterface');
		$this->assertSame('', (new AgentNodes($map))->getOutput('html'));
	}

	public function testEmptyNodeCatalogSerializesAsEmptyArray(): void {
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([]);
		$this->assertSame([], json_decode((new AgentNodes($map))->getOutput('json'), true));
	}

	public function testJsonCatalogIncludesConcreteNodePortsAndDescription(): void {
		$node = new IfNode('if-example');
		$map = $this->createMock(IClassMap::class);
		$map->expects($this->once())->method('getInstancesByInterface')->with(IAgentNode::class)->willReturn([$node]);
		$results = json_decode((new AgentNodes($map))->getOutput('json'), true);
		$this->assertCount(1, $results);
		$this->assertSame('ifnode', $results[0]['name']);
		$this->assertSame(IfNode::class, $results[0]['class']);
		$this->assertSame('condition', $results[0]['inputs'][0]['name']);
		$this->assertSame('bool', $results[0]['inputs'][0]['type']);
		$this->assertTrue($results[0]['inputs'][0]['required']);
		$this->assertSame('true', $results[0]['outputs'][0]['name']);
		$this->assertNotSame('', $results[0]['description']);
	}

	public function testOutputNameAndHelpRemainStable(): void {
		$this->assertSame('agentnodes', AgentNodes::getName());
		$this->assertSame("Help of AgentNodes\n", (new AgentNodes($this->createStub(IClassMap::class)))->getHelp());
	}
}
