<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Core;

use MissionBay\Context\AgentContext;
use MissionBay\Node\Core\TestInputNode;
use PHPUnit\Framework\TestCase;

final class TestInputNodeTest extends TestCase {

	public function testPassesThroughNestedValuesWithoutModification(): void {
		$node = new TestInputNode();
		$input = ['items' => [0, false, null]];
		$this->assertSame(['value' => $input], $node->execute(['value' => $input], [], new AgentContext()));
	}

	public function testAbsentInputDefaultsToNull(): void {
		$node = new TestInputNode();
		$this->assertSame(['value' => null], $node->execute([], [], new AgentContext()));
	}
}
