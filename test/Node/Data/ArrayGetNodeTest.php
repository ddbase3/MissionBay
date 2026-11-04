<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Data;

use MissionBay\Context\AgentContext;
use MissionBay\Node\Data\ArrayGetNode;
use PHPUnit\Framework\TestCase;

final class ArrayGetNodeTest extends TestCase {

	public function testNestedPathPreservesNullFalseAndArrays(): void {
		$node = new ArrayGetNode('lookup');
		$data = ['user' => ['disabled' => false, 'profile' => ['age' => 0], 'missing' => null]];
		$context = new AgentContext();
		$this->assertSame(['value' => false], $node->execute(['array' => $data, 'path' => 'user.disabled'], [], $context));
		$this->assertSame(['value' => ['age' => 0]], $node->execute(['array' => $data, 'path' => 'user.profile'], [], $context));
		$this->assertSame(['value' => null], $node->execute(['array' => $data, 'path' => 'user.missing'], [], $context));
	}

	public function testInvalidArrayAndPathProduceContextualErrors(): void {
		$node = new ArrayGetNode('lookup');
		$context = new AgentContext();
		$this->assertStringContainsString('Input is not an array', $node->execute(['array' => 'wrong', 'path' => 'a'], [], $context)['error']);
		$this->assertStringContainsString('Invalid or missing path', $node->execute(['array' => [], 'path' => []], [], $context)['error']);
		$this->assertStringContainsString('lookup', $node->execute(['array' => [], 'path' => ''], [], $context)['error']);
	}

	public function testMissingIntermediatePathReturnsError(): void {
		$node = new ArrayGetNode();
		$context = new AgentContext();
		$this->assertStringContainsString('Path not found: a.b', $node->execute(['array' => ['a' => 0], 'path' => 'a.b'], [], $context)['error']);
		$this->assertStringContainsString('Path not found: a.b', $node->execute(['array' => [], 'path' => 'a.b'], [], $context)['error']);
	}

	public function testNodeIdentityAndPortSchemaRemainStable(): void {
		$node = new ArrayGetNode('a');
		$this->assertSame('arraygetnode', ArrayGetNode::getName());
		$this->assertSame(['array', 'path'], array_map(static fn($port): string => $port->name, $node->getInputDefinitions()));
	}
}
