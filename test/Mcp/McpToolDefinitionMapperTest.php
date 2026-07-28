<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp;

use MissionBay\Mcp\McpToolDefinitionMapper;
use PHPUnit\Framework\TestCase;

final class McpToolDefinitionMapperTest extends TestCase {

	public function testTopLevelReadOnlyHintProducesCompleteConsistentMcpAnnotations(): void {
		$tool = (new McpToolDefinitionMapper())->toMcpTool([
			'type' => 'function',
			'readOnlyHint' => true,
			'function' => [
				'name' => 'list_plugins',
				'description' => 'Lists plugins.',
				'parameters' => ['type' => 'object', 'properties' => []]
			]
		]);

		$this->assertSame([
			'readOnlyHint' => true,
			'destructiveHint' => false,
			'idempotentHint' => true,
			'openWorldHint' => true
		], $tool['annotations']);
	}

	public function testUnannotatedToolUsesConservativeCompleteMcpAnnotations(): void {
		$tool = (new McpToolDefinitionMapper())->toMcpTool([
			'type' => 'function',
			'function' => [
				'name' => 'unknown_operation',
				'description' => 'An operation without declared safety semantics.',
				'parameters' => ['type' => 'object', 'properties' => []]
			]
		]);

		$this->assertSame([
			'readOnlyHint' => false,
			'destructiveHint' => true,
			'idempotentHint' => false,
			'openWorldHint' => true
		], $tool['annotations']);
	}

	public function testExplicitMcpAnnotationsArePreserved(): void {
		$tool = (new McpToolDefinitionMapper())->toMcpTool([
			'type' => 'function',
			'annotations' => [
				'readOnlyHint' => false,
				'destructiveHint' => false,
				'idempotentHint' => true,
				'openWorldHint' => false
			],
			'function' => [
				'name' => 'set_state',
				'description' => 'Changes state.',
				'parameters' => ['type' => 'object', 'properties' => []]
			]
		]);

		$this->assertSame([
			'readOnlyHint' => false,
			'destructiveHint' => false,
			'idempotentHint' => true,
			'openWorldHint' => false
		], $tool['annotations']);
	}

	public function testExplicitTopLevelHintOverridesNestedFallbackAnnotation(): void {
		$tool = (new McpToolDefinitionMapper())->toMcpTool([
			'type' => 'function',
			'readOnlyHint' => true,
			'annotations' => [
				'readOnlyHint' => false
			],
			'function' => [
				'name' => 'get_state',
				'description' => 'Reads state.',
				'parameters' => ['type' => 'object', 'properties' => []]
			]
		]);

		$this->assertTrue($tool['annotations']['readOnlyHint']);
		$this->assertFalse($tool['annotations']['destructiveHint']);
		$this->assertTrue($tool['annotations']['idempotentHint']);
	}
}
