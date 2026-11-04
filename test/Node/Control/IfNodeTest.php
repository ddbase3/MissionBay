<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Control;

use MissionBay\Context\AgentContext;
use MissionBay\Node\Control\IfNode;
use PHPUnit\Framework\TestCase;

final class IfNodeTest extends TestCase {

	public function testTrueAndFalseRouteToDifferentPorts(): void {
		$node = new IfNode('decision');
		$context = new AgentContext();
		$this->assertSame(['true' => 1], $node->execute(['condition' => true], [], $context));
		$this->assertSame(['false' => 1], $node->execute(['condition' => false], [], $context));
	}

	public function testAbsentConditionProducesContextualError(): void {
		$result = (new IfNode('decision'))->execute([], [], new AgentContext());
		$this->assertSame(['error' => '[ ifnode | decision ] Missing "condition" input'], $result);
	}

	public function testNonBooleanValuesAreRejectedWithoutCoercion(): void {
		$node = new IfNode('gate');
		foreach ([0, 1, '', 'false', null, []] as $invalid) {
			$this->assertStringContainsString('must be boolean', $node->execute(['condition' => $invalid], [], new AgentContext())['error']);
		}
	}

	public function testPortsAndTechnicalNameDescribeBranchOutputs(): void {
		$node = new IfNode();
		$this->assertSame('ifnode', IfNode::getName());
		$this->assertSame(['condition'], array_map(static fn($port) => $port->name, $node->getInputDefinitions()));
		$this->assertSame(['true', 'false', 'error'], array_map(static fn($port) => $port->name, $node->getOutputDefinitions()));
	}
}
