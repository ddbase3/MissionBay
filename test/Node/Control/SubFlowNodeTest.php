<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Control;

use MissionBay\Api\IAgentFlow;
use MissionBay\Context\AgentContext;
use MissionBay\Node\Control\SubFlowNode;
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
