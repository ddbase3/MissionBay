<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Control;

use MissionBay\Context\AgentContext;
use MissionBay\Node\Control\NoActionNode;
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
