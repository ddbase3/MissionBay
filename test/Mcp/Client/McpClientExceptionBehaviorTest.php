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

use Base3\MissionBay\Mcp\Client\McpClientException;
use PHPUnit\Framework\TestCase;

final class McpClientExceptionBehaviorTest extends TestCase {

	public function testTransportFailureExposesOnlyHttpStatus(): void {
		$error = new McpClientException('gateway unavailable', 503);
		$this->assertSame('gateway unavailable', $error->getMessage());
		$this->assertSame(503, $error->getHttpStatus());
		$this->assertNull($error->getJsonRpcCode());
		$this->assertSame(503, $error->getCode());
	}

	public function testJsonRpcFailureKeepsHttpStatusSeparately(): void {
		$error = new McpClientException('method missing', 400, -32601);
		$this->assertSame(400, $error->getHttpStatus());
		$this->assertSame(-32601, $error->getJsonRpcCode());
		$this->assertSame(-32601, $error->getCode());
	}

	public function testFailureChainsOriginalCause(): void {
		$previous = new \RuntimeException('origin');
		$error = new McpClientException('transport failed', 0, null, $previous);
		$this->assertSame($previous, $error->getPrevious());
		$this->assertSame(0, $error->getCode());
	}
}
