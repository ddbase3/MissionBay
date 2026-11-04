<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Control;

use MissionBay\Context\AgentContext;
use MissionBay\Node\Control\SwitchNode;
use PHPUnit\Framework\TestCase;

final class SwitchNodeTest extends TestCase {

	public function testExplicitCaseMatchesStrictly(): void {
		$node = new SwitchNode();
		$context = new AgentContext();
		$this->assertSame(['blue' => 1], $node->execute(['value' => 'blue', 'cases' => ['red', 'blue']], [], $context));
		$this->assertSame(['default' => 1], $node->execute(['value' => '1', 'cases' => [1]], [], $context));
	}

	public function testUnmatchedCaseGoesToDefault(): void {
		$node = new SwitchNode();
		$this->assertSame(['default' => 1], $node->execute(['value' => 'green', 'cases' => ['red']], [], new AgentContext()));
	}

	public function testFailSentinelWorksOnlyWithEmptyCaseList(): void {
		$node = new SwitchNode();
		$this->assertSame(['fail' => 1], $node->execute(['value' => 'fail'], [], new AgentContext()));
		$this->assertSame(['default' => 1], $node->execute(['value' => 'fail', 'cases' => ['other']], [], new AgentContext()));
	}

	public function testMissingValueAndInvalidCasesAreHandled(): void {
		$node = new SwitchNode('route');
		$this->assertStringContainsString('must be string', $node->execute(['value' => 5], [], new AgentContext())['error']);
		$this->assertSame(['default' => 1], $node->execute(['value' => 'x', 'cases' => 'x'], [], new AgentContext()));
	}
}
