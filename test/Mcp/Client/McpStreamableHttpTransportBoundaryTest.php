<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp\Client;

use MissionBay\Api\IMcpTransport;
use MissionBay\Dto\Mcp\McpHttpRequest;
use MissionBay\Mcp\Client\McpClientException;
use MissionBay\Mcp\Client\McpStreamableHttpTransport;
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
