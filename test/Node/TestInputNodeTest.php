<?php declare(strict_types=1);

namespace MissionBay\Test\Node;

use PHPUnit\Framework\TestCase;
use MissionBay\Node\TestInputNode;
use MissionBay\Context\AgentContext;
use MissionBay\Memory\NoMemory;

class TestInputNodeTest extends TestCase
{
	public function testExecutePassesThroughInput(): void
	{
		$node = new TestInputNode();
		$node->setId('input1');

		$context = new AgentContext(new NoMemory());
		$inputs = ['value' => 'hello'];

		$output = $node->execute($inputs, $context);

		$this->assertArrayHasKey('value', $output);
		$this->assertSame('hello', $output['value']);
	}

	public function testExecuteWithNoInputReturnsNull(): void
	{
		$node = new TestInputNode();
		$node->setId('input2');

		$context = new AgentContext(new NoMemory());
		$output = $node->execute([], $context);

		$this->assertArrayHasKey('value', $output);
		$this->assertNull($output['value']);
	}
}

