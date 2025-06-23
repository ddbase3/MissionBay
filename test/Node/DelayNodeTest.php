<?php declare(strict_types=1);

namespace MissionBay\Test\Node;

use PHPUnit\Framework\TestCase;
use MissionBay\Node\DelayNode;
use MissionBay\Context\AgentContext;
use MissionBay\Memory\NoMemory;

class DelayNodeTest extends TestCase
{
	public function testExecuteWithValidSeconds(): void
	{
		$node = new DelayNode();
		$node->setId('delay1');

		$context = new AgentContext(new NoMemory());

		// Verwende 0 Sekunden, um echte Verzögerung im Test zu vermeiden
		$output = $node->execute(['seconds' => 0], $context);

		$this->assertIsArray($output);
		$this->assertArrayHasKey('done', $output);
		$this->assertTrue($output['done']);
	}

	public function testExecuteWithInvalidInputReturnsError(): void
	{
		$node = new DelayNode();
		$node->setId('delay2');

		$context = new AgentContext(new NoMemory());

		$output = $node->execute(['seconds' => -5], $context);
		$this->assertArrayHasKey('error', $output);
		$this->assertStringContainsString('Invalid', $output['error']);

		$output = $node->execute(['seconds' => 999], $context);
		$this->assertArrayHasKey('error', $output);
		$this->assertStringContainsString('Invalid', $output['error']);

		$output = $node->execute(['seconds' => 'not-a-number'], $context);
		$this->assertArrayHasKey('error', $output);
		$this->assertStringContainsString('Invalid', $output['error']);
	}
}

