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

namespace Base3\MissionBay\Test\Mcp\Client;

use Base3\MissionBay\Api\IMcpTransport;
use Base3\MissionBay\Dto\Mcp\McpHttpRequest;
use Base3\MissionBay\Mcp\Client\McpClientException;
use Base3\MissionBay\Mcp\Client\McpStreamableHttpTransport;
use PHPUnit\Framework\TestCase;

final class McpStreamableHttpTransportBoundaryTest extends TestCase {

	public function testTransportImplementsTheMcpRequestContract(): void {
		$transport = new McpStreamableHttpTransport();
		$this->assertTrue($transport instanceof IMcpTransport);
		$this->assertSame('mcpstreamablehttptransport', $transport::getName());
	}

	public function testMalformedHeaderCannotReachAnHttpEndpoint(): void {
		$request = new McpHttpRequest(
			'https://invalid.example.test/mcp',
			'{"jsonrpc":"2.0","method":"ping","id":1}',
			["X-Injected\nHeader" => 'malformed'],
			1,
			1,
			1024,
			true
		);
		$this->expectException(McpClientException::class);
		(new McpStreamableHttpTransport())->send($request);
	}

	public function testHeaderValueWithLineBreakIsRejectedBeforeNetworkAccess(): void {
		$request = new McpHttpRequest('https://invalid.example.test/mcp', '{}', ['X-Token' => "first\r\nsecond"], 1, 1, 1024, true);
		$this->expectException(McpClientException::class);
		(new McpStreamableHttpTransport())->send($request);
	}
}
