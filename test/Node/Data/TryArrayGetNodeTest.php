<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Data;

use MissionBay\Context\AgentContext;
use MissionBay\Node\Data\TryArrayGetNode;
use PHPUnit\Framework\TestCase;

final class TryArrayGetNodeTest extends TestCase {

	public function testNestedValuesAndNullAreReturned(): void {
		$node = new TryArrayGetNode();
		$context = new AgentContext();
		$this->assertSame(['value' => 12], $node->execute(['array' => ['a' => ['b' => 12]], 'path' => 'a.b'], [], $context));
		$this->assertSame(['value' => null], $node->execute(['array' => ['a' => null], 'path' => 'a'], [], $context));
	}

	public function testMissingKeysAndScalarIntermediateValuesYieldNoOutput(): void {
		$node = new TryArrayGetNode();
		$context = new AgentContext();
		$this->assertSame([], $node->execute(['array' => ['a' => 4], 'path' => 'a.b'], [], $context));
		$this->assertSame([], $node->execute(['array' => [], 'path' => 'missing'], [], $context));
	}

	public function testWrongInputTypesAndMissingInputsYieldNoOutput(): void {
		$node = new TryArrayGetNode();
		$context = new AgentContext();
		$this->assertSame([], $node->execute(['array' => 'text', 'path' => 'a'], [], $context));
		$this->assertSame([], $node->execute(['array' => [], 'path' => []], [], $context));
		$this->assertSame([], $node->execute([], [], $context));
	}
}
