<?php declare(strict_types=1);

namespace MissionBay\Test\Node;

use PHPUnit\Framework\TestCase;
use MissionBay\Node\StringReverserNode;
use MissionBay\Context\AgentContext;
use MissionBay\Memory\NoMemory;

class StringReverserNodeTest extends TestCase
{
	public function testExecuteReturnsReversedString(): void
	{
		$node = new StringReverserNode();
		$node->setId('test');

		$context = new AgentContext(new NoMemory());
		$inputs = ['text' => 'MissionBay'];

		$output = $node->execute($inputs, $context);

		$this->assertArrayHasKey('reversed', $output);
		$this->assertSame('yaBnoissiM', $output['reversed']);
	}

	public function testExecuteWithEmptyInputReturnsEmpty(): void
	{
		$node = new StringReverserNode();
		$node->setId('test');

		$context = new AgentContext(new NoMemory());
		$output = $node->execute([], $context);

		$this->assertArrayHasKey('reversed', $output);
		$this->assertSame('', $output['reversed']);
	}
}

