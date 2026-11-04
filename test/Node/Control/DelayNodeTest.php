<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Control;

use MissionBay\Context\AgentContext;
use MissionBay\Node\Control\DelayNode;
use PHPUnit\Framework\TestCase;

final class DelayNodeTest extends TestCase {

	public function testZeroDelayReturnsSuccessWithoutWaiting(): void {
		$node = new DelayNode();
		$this->assertSame(['done' => true], $node->execute(['seconds' => 0], [], new AgentContext()));
	}

	public function testNegativeAndExcessiveDelaysAreRejected(): void {
		$node = new DelayNode();
		$context = new AgentContext();
		foreach ([-1, 61, 'not-a-time'] as $invalid) {
			$this->assertStringContainsString('Invalid delay time', $node->execute(['seconds' => $invalid], [], $context)['error']);
		}
	}

	public function testNumericZeroStringIsAccepted(): void {
		$node = new DelayNode();
		$this->assertSame(['done' => true], $node->execute(['seconds' => '0'], [], new AgentContext()));
	}
}
