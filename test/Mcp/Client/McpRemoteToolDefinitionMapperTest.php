<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp\Client;

use MissionBay\Mcp\Client\McpRemoteToolDefinitionMapper;
use PHPUnit\Framework\TestCase;

final class McpRemoteToolDefinitionMapperTest extends TestCase {

	public function testCompleteReadOnlyHintsExposeReadOnlyToolWithoutApproval(): void {
		$definition = (new McpRemoteToolDefinitionMapper())->toAgentTool([
			'name' => 'read_repository',
			'inputSchema' => ['type' => 'object'],
			'annotations' => [
				'readOnlyHint' => true,
				'destructiveHint' => false,
				'idempotentHint' => true,
				'openWorldHint' => true
			]
		]);

		$this->assertTrue($definition['readOnlyHint']);
		$this->assertFalse($definition['mutation']);
		$this->assertFalse($definition['requiresApproval']);
		$this->assertTrue($definition['annotations']['mcpHintsComplete']);
		$this->assertSame([], $definition['annotations']['mcpMissingHints']);
		$this->assertSame('medium', $definition['annotations']['riskHint']);
		$this->assertTrue($definition['annotations']['openWorldHint']);
	}

	public function testMissingHintForcesHighRiskApproval(): void {
		$definition = (new McpRemoteToolDefinitionMapper())->toAgentTool([
			'name' => 'read_repository',
			'inputSchema' => ['type' => 'object'],
			'annotations' => [
				'readOnlyHint' => true,
				'destructiveHint' => false,
				'idempotentHint' => true
			]
		]);

		$this->assertFalse($definition['readOnlyHint']);
		$this->assertTrue($definition['mutation']);
		$this->assertTrue($definition['requiresApproval']);
		$this->assertFalse($definition['annotations']['mcpHintsComplete']);
		$this->assertSame(['openWorldHint'], $definition['annotations']['mcpMissingHints']);
		$this->assertSame('high', $definition['annotations']['riskHint']);
	}

	public function testCompleteAdditiveIdempotentClosedToolUsesMediumRiskApproval(): void {
		$definition = (new McpRemoteToolDefinitionMapper())->toAgentTool([
			'name' => 'append_note',
			'inputSchema' => ['type' => 'object'],
			'annotations' => [
				'readOnlyHint' => false,
				'destructiveHint' => false,
				'idempotentHint' => true,
				'openWorldHint' => false
			]
		]);

		$this->assertTrue($definition['mutation']);
		$this->assertTrue($definition['requiresApproval']);
		$this->assertSame('medium', $definition['annotations']['riskHint']);
	}

	public function testContradictoryHintsForceHighRiskApproval(): void {
		$definition = (new McpRemoteToolDefinitionMapper())->toAgentTool([
			'name' => 'contradictory_tool',
			'inputSchema' => ['type' => 'object'],
			'annotations' => [
				'readOnlyHint' => true,
				'destructiveHint' => true,
				'idempotentHint' => true,
				'openWorldHint' => false
			]
		]);

		$this->assertTrue($definition['mutation']);
		$this->assertTrue($definition['requiresApproval']);
		$this->assertTrue($definition['annotations']['mcpHintsContradictory']);
		$this->assertSame('high', $definition['annotations']['riskHint']);
	}
}
