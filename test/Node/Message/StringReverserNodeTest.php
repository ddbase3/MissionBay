<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Message;

use MissionBay\Context\AgentContext;
use MissionBay\Node\Message\StringReverserNode;
use PHPUnit\Framework\TestCase;

final class StringReverserNodeTest extends TestCase {

	public function testReversesAsciiAndPreservesWhitespace(): void {
		$node = new StringReverserNode();
		$this->assertSame(['reversed' => 'cba'], $node->execute(['text' => 'abc'], [], new AgentContext()));
		$this->assertSame(['reversed' => ' cba '], $node->execute(['text' => ' abc '], [], new AgentContext()));
	}

	public function testMissingInputDefaultsToEmptyString(): void {
		$this->assertSame(['reversed' => ''], (new StringReverserNode())->execute([], [], new AgentContext()));
	}

	public function testPortAndIdentityAreDeclared(): void {
		$node = new StringReverserNode();
		$this->assertSame('stringreversernode', StringReverserNode::getName());
		$this->assertSame('text', $node->getInputDefinitions()[0]->name);
		$this->assertSame('reversed', $node->getOutputDefinitions()[0]->name);
	}
}
