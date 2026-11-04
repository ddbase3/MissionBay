<?php declare(strict_types=1);

namespace MissionBay\Test\Content;

use Base3\Api\IClassMap;
use MissionBay\Api\IAgentNode;
use MissionBay\Content\AgentNodes;
use MissionBay\Node\Control\IfNode;
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
