<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp;

use AssistantFoundation\Api\IAgentContext;
use Base3\Logger\Api\ILogger;
use InvalidArgumentException;
use MissionBay\Api\IAgentTool;
use MissionBay\Mcp\McpToolCatalog;
use MissionBay\Mcp\McpToolDefinitionMapper;
use PHPUnit\Framework\TestCase;

final class McpToolCatalogTest extends TestCase {

	public function testCatalogMapsToolDefinitionsAndRoutesCallsToTheirOwners(): void {
		$tool = $this->createStub(IAgentTool::class);
		$tool->method('getToolDefinitions')->willReturn([
			['type' => 'function', 'function' => ['name' => 'lookup', 'description' => 'Lookup', 'parameters' => ['type' => 'object']]],
			['name' => 'status', 'readOnlyHint' => true],
			['not_a_callable_definition' => true]
		]);
		$tool->method('callTool')->willReturn(['items' => 2]);
		$catalog = new McpToolCatalog([$tool], new McpToolDefinitionMapper(), $this->createStub(ILogger::class));
		$this->assertSame(['lookup', 'status'], array_column($catalog->listTools(), 'name'));
		$this->assertSame('lookup', $catalog->getDefinition('lookup')['function']['name']);
		$this->assertSame($tool, $catalog->getTool('status'));
		$this->assertSame(['items' => 2], $catalog->call('lookup', ['query' => 'x'], $this->createStub(IAgentContext::class)));
	}

	public function testLaterToolWinsForDuplicateFunctionNames(): void {
		$first = $this->createStub(IAgentTool::class);
		$first->method('getToolDefinitions')->willReturn([['name' => 'status']]);
		$second = $this->createStub(IAgentTool::class);
		$second->method('getToolDefinitions')->willReturn([['name' => 'status']]);
		$catalog = new McpToolCatalog([$first, $second], new McpToolDefinitionMapper(), $this->createStub(ILogger::class));
		$this->assertSame($second, $catalog->getTool('status'));
		$this->assertCount(2, $catalog->listTools());
	}

	public function testUnknownToolIsNotExecuted(): void {
		$catalog = new McpToolCatalog([], new McpToolDefinitionMapper(), $this->createStub(ILogger::class));
		$this->assertSame([], $catalog->listTools());
		$this->expectException(InvalidArgumentException::class);
		$catalog->getTool('unregistered');
	}
}
