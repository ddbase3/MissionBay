<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp;

use Base3\Api\IRequest;
use Base3\Logger\Api\ILogger;
use MissionBay\Mcp\McpHttpGuard;
use PHPUnit\Framework\TestCase;

final class McpHttpGuardProtocolEdgesTest extends TestCase {

	public function testMissingMethodFallsBackToWebPostRequestContext(): void {
		$this->assertNull($this->guard([], IRequest::CONTEXT_WEB_POST)->checkBeforePayload());
	}

	public function testMissingMethodRejectsReadOnlyWebGetContext(): void {
		$this->assertSame(405, $this->guard([], IRequest::CONTEXT_WEB_GET)->checkBeforePayload()['status']);
	}

	public function testAnyAcceptHeaderWildcardAllowsJsonResponse(): void {
		$this->assertNull($this->guard(['REQUEST_METHOD' => 'POST', 'HTTP_ACCEPT' => '*/*'])->checkBeforePayload());
	}

	public function testLegacyProtocolHeaderNameIsSupported(): void {
		$guard = $this->guard(['REQUEST_METHOD' => 'POST', 'MCP_PROTOCOL_VERSION' => '2025-03-26']);
		$this->assertNull($guard->checkAfterPayload(['method' => 'tools/list']));
	}

	public function testUnsupportedLegacyProtocolVersionFailsClosed(): void {
		$guard = $this->guard(['REQUEST_METHOD' => 'POST', 'MCP_PROTOCOL_VERSION' => '2000-01-01']);
		$issue = $guard->checkAfterPayload(['method' => 'tools/list']);
		$this->assertSame(400, $issue['status']);
		$this->assertSame(-32006, $issue['body']['error']['code']);
	}

	public function testInvalidBatchMemberDoesNotBypassProtocolVersionCheck(): void {
		$guard = $this->guard(['REQUEST_METHOD' => 'POST', 'HTTP_MCP_PROTOCOL_VERSION' => '2000-01-01']);
		$this->assertSame(-32006, $guard->checkAfterPayload([['method' => 'initialize'], false])['body']['error']['code']);
	}

	private function guard(array $server, string $context = IRequest::CONTEXT_WEB_POST): McpHttpGuard {
		$request = $this->createStub(IRequest::class);
		$request->method('server')->willReturnCallback(static function(string $key, mixed $default = null) use ($server): mixed {
			return $server[$key] ?? '';
		});
		$request->method('getContext')->willReturn($context);
		return new McpHttpGuard($request, $this->createStub(ILogger::class));
	}
}
