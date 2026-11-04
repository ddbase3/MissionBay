<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Core;

use MissionBay\Context\AgentContext;
use MissionBay\Node\Core\SetContextVarNode;
use PHPUnit\Framework\TestCase;

final class SetContextVarNodeTest extends TestCase {

	public function testWritesAnyValueToSharedContext(): void {
		$node = new SetContextVarNode();
		$context = new AgentContext();
		$this->assertSame(['success' => true], $node->execute(['key' => 'data', 'value' => [1, 2]], [], $context));
		$this->assertSame([1, 2], $context->getVar('data'));
		$this->assertSame(['success' => true], $node->execute(['key' => 'data', 'value' => false], [], $context));
		$this->assertSame(false, $context->getVar('data'));
	}

	public function testOmittedValueWritesNull(): void {
		$context = new AgentContext();
		$result = (new SetContextVarNode())->execute(['key' => 'unset'], [], $context);
		$this->assertSame(['success' => true], $result);
		$this->assertSame(['unset'], $context->listVars());
		$this->assertNull($context->getVar('unset'));
	}

	public function testInvalidKeyDoesNotModifyContext(): void {
		$context = new AgentContext();
		$result = (new SetContextVarNode())->execute(['key' => 7, 'value' => 'secret'], [], $context);
		$this->assertStringContainsString('must be a string', $result['error']);
		$this->assertSame([], $context->listVars());
	}
}
