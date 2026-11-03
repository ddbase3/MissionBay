<?php declare(strict_types=1);

namespace MissionBay\Test\Flow;

use AssistantFoundation\Api\IAgentContext;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentNode;
use MissionBay\Api\IAgentNodeFactory;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Flow\StrictFlow;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StrictFlowTest extends TestCase {

	public function testConnectionsInputsAndResourceDocksAreRetained(): void {
		$flow = $this->flow();
		$flow->addConnection('start', 'text', 'end', 'prompt');
		$flow->addInitialInput('end', 'language', 'de');
		$flow->addDockConnection('end', 'memory', 'm1');
		$flow->addResourceDockConnection('m1', 'source', 'r1');
		$this->assertCount(1, $flow->getConnections());
		$this->assertSame(['end' => ['language' => 'de']], $flow->getInitialInputs());
		$this->assertSame(['memory' => ['m1']], $flow->getDockConnections('end'));
		$this->assertSame([], $flow->getDockConnections('other'));
		$this->assertSame(['source' => ['r1']], $flow->getResourceDockConnections('m1'));
		$this->assertSame([], $flow->getResourceDockConnections('other'));
	}

	public function testReadinessMappingAndFirstSuccessor(): void {
		$flow = $this->flow();
		$flow->addConnection('start', 'text', 'end', 'prompt');
		$flow->addConnection('start', 'length', 'end', 'size');
		$this->assertFalse($flow->isReady('end', ['prompt' => 'hello']));
		$this->assertTrue($flow->isReady('end', ['prompt' => 'hello', 'size' => 5]));
		$this->assertTrue($flow->isReady('start', []));
		$this->assertSame('end', $flow->getNextNode('start', ['text' => 'hello']));
		$this->assertNull($flow->getNextNode('other', []));
		$this->assertSame(['prompt' => 'hello', 'size' => null], $flow->mapInputs('start', 'end', ['text' => 'hello']));
	}

	public function testMissingContextIsRejectedBeforeExecutingNodes(): void {
		$this->expectException(RuntimeException::class);
		$this->flow()->run([]);
	}

	public function testRunExecutesReadyNodeAndAddsOutputDefaults(): void {
		$node = $this->createStub(IAgentNode::class);
		$node->method('getId')->willReturn('terminal');
		$node->method('getInputDefinitions')->willReturn([new AgentNodePort('text')]);
		$node->method('getOutputDefinitions')->willReturn([new AgentNodePort('status', default: 'ok')]);
		$node->method('getConfig')->willReturn([]);
		$node->method('execute')->willReturn(['value' => 42]);
		$flow = $this->flow();
		$flow->setContext($this->createStub(IAgentContext::class));
		$flow->addNode($node);
		$flow->addInitialInput('terminal', 'text', 'test');
		$this->assertSame(['terminal' => ['value' => 42, 'status' => 'ok']], $flow->run([]));
	}

	public function testRunReturnsRequiredInputFailureWithoutExecutingNode(): void {
		$node = $this->createStub(IAgentNode::class);
		$node->method('getId')->willReturn('terminal');
		$node->method('getInputDefinitions')->willReturn(['required']);
		$node->method('getConfig')->willReturn([]);
		$flow = $this->flow();
		$flow->setContext($this->createStub(IAgentContext::class));
		$flow->addNode($node);
		$this->assertSame(['terminal' => ['error' => "Missing required input 'required' for node 'terminal'"]], $flow->run([]));
	}

	private function flow(): StrictFlow {
		return new StrictFlow(
			$this->createStub(IAgentNodeFactory::class),
			$this->createStub(IAgentResourceFactory::class),
			$this->createStub(IAgentConfigValueResolver::class)
		);
	}
}
