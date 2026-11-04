<?php declare(strict_types=1);

namespace MissionBay\Test\Flow;

use Base3\Api\IClassMap;
use Base3\Configuration\Api\IConfiguration;
use MissionBay\Api\IAgentNode;
use MissionBay\Api\IAgentNodeFactory;
use MissionBay\Api\IAgentResource;
use MissionBay\Flow\DynamicAiFlow;
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
