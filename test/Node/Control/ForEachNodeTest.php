<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Control;

use MissionBay\Api\IAgentNode;
use MissionBay\Context\AgentContext;
use MissionBay\Node\Control\ForEachNode;
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
