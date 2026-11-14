<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace Base3\MissionBay\Test\Mcp;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\CoreFoundation\Logger\Api\ILogger;
use InvalidArgumentException;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Mcp\McpToolCatalog;
use Base3\MissionBay\Mcp\McpToolDefinitionMapper;
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
