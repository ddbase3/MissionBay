<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Control;

use MissionBay\Api\IAgentNode;
use MissionBay\Context\AgentContext;
use MissionBay\Node\Control\LoopNode;
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
