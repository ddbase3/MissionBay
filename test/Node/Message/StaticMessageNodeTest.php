<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Message;

use MissionBay\Context\AgentContext;
use MissionBay\Node\Message\StaticMessageNode;
use PHPUnit\Framework\TestCase;

final class StaticMessageNodeTest extends TestCase {

	public function testOutputsTextAndDefaultsToEmptyString(): void {
		$node = new StaticMessageNode();
		$this->assertSame(['message' => 'hello'], $node->execute(['text' => 'hello'], [], new AgentContext()));
		$this->assertSame(['message' => ''], $node->execute([], [], new AgentContext()));
	}

	public function testScalarInputIsStringified(): void {
		$node = new StaticMessageNode();
		$this->assertSame(['message' => '0'], $node->execute(['text' => 0], [], new AgentContext()));
		$this->assertSame(['message' => '1'], $node->execute(['text' => true], [], new AgentContext()));
	}
}
