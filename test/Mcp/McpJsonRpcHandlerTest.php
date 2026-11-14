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

use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IAgentComponentPresetMaterializer;
use Base3\MissionBay\Mcp\McpJsonRpcHandler;
use Base3\MissionBay\Mcp\McpToolDefinitionMapper;
use Base3\MissionBay\Mcp\McpToolPresetMaterializer;
use Base3\MissionBay\Mcp\McpToolProfileRepository;
use Base3\MissionBay\Mcp\McpToolResultMapper;
use PHPUnit\Framework\TestCase;

final class McpJsonRpcHandlerTest extends TestCase {

	public function testMissingMethodReturnsJsonRpcInvalidRequestError(): void {
		$result = $this->handler()->handle(['jsonrpc' => '2.0', 'id' => 17], 'profile');
		$this->assertSame(17, $result['id']);
		$this->assertSame('2.0', $result['jsonrpc']);
		$this->assertSame(-32600, $result['error']['code']);
		$this->assertStringContainsString('missing method', $result['error']['message']);
	}

	public function testUnknownMethodReturnsMethodNotFoundError(): void {
		$result = $this->handler()->handle(['id' => 'request-1', 'method' => 'unknown/method'], 'profile');
		$this->assertSame('request-1', $result['id']);
		$this->assertSame(-32601, $result['error']['code']);
		$this->assertStringContainsString('unknown/method', $result['error']['message']);
	}

	public function testInitializeNegotiatesProtocolWithoutMaterializingTools(): void {
		$handler = $this->handler();
		$default = $handler->handle(['id' => 1, 'method' => 'initialize'], 'profile');
		$this->assertSame('2025-11-25', $default['result']['protocolVersion']);
		$this->assertSame('MissionBay MCP', $default['result']['serverInfo']['name']);
		$this->assertFalse($default['result']['capabilities']['tools']['listChanged']);
		$compatibility = $handler->handle(['id' => 2, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-03-26']], 'profile');
		$this->assertSame('2025-03-26', $compatibility['result']['protocolVersion']);
	}

	public function testUnsupportedProtocolVersionReturnsInvalidParametersError(): void {
		$result = $this->handler()->handle(['id' => 3, 'method' => 'initialize', 'params' => ['protocolVersion' => '2000-01-01']], 'profile');
		$this->assertSame(-32602, $result['error']['code']);
		$this->assertStringContainsString('Unsupported MCP protocol version', $result['error']['message']);
	}

	public function testPingReturnsEmptyJsonRpcObject(): void {
		$result = $this->handler()->handle(['id' => 'ping-1', 'method' => 'ping'], 'profile');
		$this->assertSame('ping-1', $result['id']);
		$this->assertSame([], get_object_vars($result['result']));
	}

	public function testNotificationsProduceNoResponse(): void {
		$handler = $this->handler();
		$this->assertNull($handler->handle(['method' => 'notifications/initialized'], 'profile'));
		$this->assertNull($handler->handle(['method' => 'notifications/cancelled', 'params' => ['requestId' => 4, 'reason' => 'user cancelled']], 'profile'));
	}

	private function handler(): McpJsonRpcHandler {
		$logger = $this->createStub(ILogger::class);
		return new McpJsonRpcHandler(
			new McpToolProfileRepository($this->createStub(ISettingsStore::class)),
			new McpToolPresetMaterializer($this->createStub(IAgentComponentPresetMaterializer::class), $logger),
			new McpToolDefinitionMapper(),
			new McpToolResultMapper(),
			$this->createStub(IClassMap::class),
			$logger
		);
	}
}
