<?php declare(strict_types=1);

namespace MissionBay\Test\Node\Data;

use MissionBay\Context\AgentContext;
use MissionBay\Node\Data\JsonToArrayNode;
use PHPUnit\Framework\TestCase;

final class JsonToArrayNodeTest extends TestCase {

	public function testNestedJsonDecodesAsAssociativeArray(): void {
		$node = new JsonToArrayNode();
		$this->assertSame(['array' => ['user' => ['id' => 5], 'enabled' => false]], $node->execute(['json' => '{"user":{"id":5},"enabled":false}'], [], new AgentContext()));
	}

	public function testValidJsonScalarsAreNotDiscarded(): void {
		$node = new JsonToArrayNode();
		$context = new AgentContext();
		$this->assertSame(['array' => null], $node->execute(['json' => 'null'], [], $context));
		$this->assertSame(['array' => false], $node->execute(['json' => 'false'], [], $context));
		$this->assertSame(['array' => [1, 2]], $node->execute(['json' => '[1,2]'], [], $context));
	}

	public function testInvalidJsonReturnsParserError(): void {
		$node = new JsonToArrayNode('decoder');
		$result = $node->execute(['json' => '{ broken'], [], new AgentContext());
		$this->assertArrayHasKey('error', $result);
		$this->assertStringContainsString('Invalid JSON', $result['error']);
		$this->assertStringContainsString('decoder', $result['error']);
	}

	public function testMissingJsonIsReportedAsError(): void {
		$node = new JsonToArrayNode();
		$this->assertArrayHasKey('error', $node->execute([], [], new AgentContext()));
	}
}
