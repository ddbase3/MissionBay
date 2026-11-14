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

namespace Base3\MissionBay\Test\Dto\Mcp;

use Base3\MissionBay\Dto\Mcp\McpHttpRequest;
use PHPUnit\Framework\TestCase;

final class McpHttpRequestContractTest extends TestCase {

	public function testRequestPreservesExplicitProtocolAndTransportLimits(): void {
		$headers = ['Content-Type' => 'application/json', 'Authorization' => 'Bearer test'];
		$request = new McpHttpRequest('https://example.org/mcp', '{"id":1}', $headers, 4, 17, 32768, true);
		$this->assertSame('https://example.org/mcp', $request->getEndpoint());
		$this->assertSame('{"id":1}', $request->getBody());
		$this->assertSame($headers, $request->getHeaders());
		$this->assertSame(4, $request->getConnectTimeout());
		$this->assertSame(17, $request->getRequestTimeout());
		$this->assertSame(32768, $request->getMaxResponseBytes());
		$this->assertTrue($request->shouldVerifyTls());
	}

	public function testRequestAllowsEmptyBodyHeadersAndDisabledTlsVerification(): void {
		$request = new McpHttpRequest('http://localhost/mcp', '', [], 0, 0, 1, false);
		$this->assertSame('', $request->getBody());
		$this->assertSame([], $request->getHeaders());
		$this->assertFalse($request->shouldVerifyTls());
		$this->assertSame(1, $request->getMaxResponseBytes());
	}
}
