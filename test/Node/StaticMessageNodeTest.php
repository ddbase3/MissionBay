<?php declare(strict_types=1);

namespace MissionBay\Test\Node;

use PHPUnit\Framework\TestCase;
use MissionBay\Node\StaticMessageNode;
use MissionBay\Context\AgentContext;
use MissionBay\Memory\NoMemory;

class StaticMessageNodeTest extends TestCase
{
	public function testExecuteReturnsMessage(): void
	{
		$node = new StaticMessageNode();
		$node->setId('msg1');

		$context = new AgentContext(new NoMemory());
		$inputs = ['text' => 'Hello, world!'];

		$output = $node->execute($inputs, $context);

		$this->assertIsArray($output);
		$this->assertArrayHasKey('message', $output);
		$this->assertSame('Hello, world!', $output['message']);
	}

	public function testExecuteWithEmptyInputReturnsEmptyString(): void
	{
		$node = new StaticMessageNode();
		$node->setId('msg2');

		$context = new AgentContext(new NoMemory());
		$output = $node->execute([], $context);

		$this->assertIsArray($output);
		$this->assertArrayHasKey('message', $output);
		$this->assertSame('', $output['message']);
	}
}

