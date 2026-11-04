<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp\Client;

use MissionBay\Mcp\Client\McpClientException;
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
