<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Core;

use MissionBay\Context\AgentContext;
use MissionBay\Node\Core\GetContextVarNode;
use PHPUnit\Framework\TestCase;

final class GetContextVarNodeTest extends TestCase {

	public function testExistingScalarAndArrayValuesAreReturned(): void {
		$node = new GetContextVarNode();
		$context = new AgentContext(vars: ['counter' => 0, 'array' => [1, 2], 'flag' => false]);
		$this->assertSame(['value' => 0], $node->execute(['key' => 'counter'], [], $context));
		$this->assertSame(['value' => false], $node->execute(['key' => 'flag'], [], $context));
		$this->assertSame(['value' => [1, 2]], $node->execute(['key' => 'array'], [], $context));
	}

	public function testUnknownAndNullValuesAreReportedMissing(): void {
		$node = new GetContextVarNode('get');
		$context = new AgentContext(vars: ['explicit-null' => null]);
		$this->assertStringContainsString('Context variable not found: unknown', $node->execute(['key' => 'unknown'], [], $context)['error']);
		$this->assertStringContainsString('Context variable not found: explicit-null', $node->execute(['key' => 'explicit-null'], [], $context)['error']);
	}

	public function testInvalidKeyTypeIsRejected(): void {
		$node = new GetContextVarNode();
		$this->assertStringContainsString('must be a string', $node->execute(['key' => 123], [], new AgentContext())['error']);
	}
}
