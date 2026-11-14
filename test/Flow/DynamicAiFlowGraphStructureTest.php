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

namespace Base3\MissionBay\Test\Flow;

use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Configuration\Api\IConfiguration;
use Base3\MissionBay\Api\IAgentNode;
use Base3\MissionBay\Api\IAgentNodeFactory;
use Base3\MissionBay\Api\IAgentResource;
use Base3\MissionBay\Flow\DynamicAiFlow;
use PHPUnit\Framework\TestCase;

final class DynamicAiFlowGraphStructureTest extends TestCase {

	public function testInitialStateHasNoNodesOrResources(): void {
		$flow = $this->flow($this->createStub(IAgentNodeFactory::class));
		$this->assertSame([], $flow->getNodes());
		$this->assertSame([], $flow->getResources());
		$this->assertSame([], $flow->getAllDockConnections());
	}

	public function testNodesAndResourcesAreIndexedByInstanceId(): void {
		$flow = $this->flow($this->createStub(IAgentNodeFactory::class));
		$node = $this->createStub(IAgentNode::class);
		$node->method('getId')->willReturn('step');
		$resource = $this->createStub(IAgentResource::class);
		$resource->method('getId')->willReturn('search');
		$flow->addNode($node);
		$flow->addResource($resource);
		$this->assertSame(['step' => $node], $flow->getNodes());
		$this->assertSame(['search' => $resource], $flow->getResources());
	}

	public function testMultipleDockConnectionsRetainInsertionOrder(): void {
		$flow = $this->flow($this->createStub(IAgentNodeFactory::class));
		$flow->addDockConnection('step', 'tools', 'first');
		$flow->addDockConnection('step', 'tools', 'second');
		$flow->addDockConnection('another', 'chatmodel', 'third');
		$this->assertSame(['tools' => ['first', 'second']], $flow->getDockConnections('step'));
		$this->assertSame([], $flow->getDockConnections('absent'));
		$this->assertCount(2, $flow->getAllDockConnections());
	}

	public function testFromArraySkipsNodesWithoutTypeOrId(): void {
		$factory = $this->createMock(IAgentNodeFactory::class);
		$factory->expects($this->never())->method('createNode');
		$flow = $this->flow($factory);
		$this->assertSame($flow, $flow->fromArray(['nodes' => [[], ['id' => 'one'], ['type' => 'type']]]));
		$this->assertSame([], $flow->getNodes());
	}

	public function testFromArrayBuildsDockConnectionsOnResolvedNode(): void {
		$node = $this->createMock(IAgentNode::class);
		$node->expects($this->once())->method('setId')->with('node-a');
		$node->method('getId')->willReturn('node-a');
		$factory = $this->createMock(IAgentNodeFactory::class);
		$factory->expects($this->once())->method('createNode')->with('parser')->willReturn($node);
		$flow = $this->flow($factory);
		$flow->fromArray(['nodes' => [['id' => 'node-a', 'type' => 'parser', 'docks' => ['tools' => ['a', 'b']]]]]);
		$this->assertSame(['node-a' => $node], $flow->getNodes());
		$this->assertSame(['tools' => ['a', 'b']], $flow->getDockConnections('node-a'));
	}

	public function testDynamicConnectionsAreNotMistakenForStaticFlowEdges(): void {
		$flow = $this->flow($this->createStub(IAgentNodeFactory::class));
		$flow->addConnection('a', 'text', 'b', 'input');
		$flow->addInitialInput('a', 'query', 'data');
		$this->assertSame([], $flow->getConnections());
		$this->assertSame([], $flow->getInitialInputs());
		$this->assertTrue($flow->isReady('any', []));
	}

	public function testRunRequiresContextBeforeMakingAnyModelCalls(): void {
		$flow = $this->flow($this->createStub(IAgentNodeFactory::class));
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Context is not set');
		$flow->run([]);
	}

	private function flow(IAgentNodeFactory $factory): DynamicAiFlow {
		return new DynamicAiFlow($factory, $this->createStub(IClassMap::class), $this->createStub(IConfiguration::class));
	}
}
