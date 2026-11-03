<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp;

use Base3\Api\IRequest;
use Base3\Logger\Api\ILogger;
use MissionBay\Mcp\McpHttpGuard;
use PHPUnit\Framework\TestCase;

final class McpHttpGuardTest extends TestCase {

	public function testPostWithJsonAcceptAndMatchingOriginIsAllowed(): void {
		$guard = $this->guard([
			'REQUEST_METHOD' => 'post', 'HTTP_ACCEPT' => 'application/json',
			'HTTP_ORIGIN' => 'https://example.test', 'HTTP_HOST' => 'EXAMPLE.TEST:443',
			'CONTENT_LENGTH' => '1024'
		]);
		$this->assertNull($guard->checkBeforePayload());
		$this->assertNull($guard->checkAfterPayload(['jsonrpc' => '2.0', 'method' => 'tools/list']));
	}

	public function testGetAndUnsupportedAcceptHeaderAreRejected(): void {
		$this->assertSame(405, $this->guard(['REQUEST_METHOD' => 'GET'])->checkBeforePayload()['status']);
		$this->assertSame('POST', $this->guard(['REQUEST_METHOD' => 'GET'])->checkBeforePayload()['headers']['Allow']);
		$this->assertSame(406, $this->guard(['REQUEST_METHOD' => 'POST', 'HTTP_ACCEPT' => 'text/event-stream'])->checkBeforePayload()['status']);
	}

	public function testCrossOriginAndOversizedBodiesAreRejected(): void {
		$this->assertSame(403, $this->guard(['REQUEST_METHOD' => 'POST', 'HTTP_ORIGIN' => 'https://attacker.test', 'HTTP_HOST' => 'example.test'])->checkBeforePayload()['status']);
		$this->assertSame(413, $this->guard(['REQUEST_METHOD' => 'POST', 'CONTENT_LENGTH' => '1048577'])->checkBeforePayload()['status']);
		$this->assertNull($this->guard(['REQUEST_METHOD' => 'POST', 'CONTENT_LENGTH' => '1048576'])->checkBeforePayload());
		$this->assertNull($this->guard(['REQUEST_METHOD' => 'POST', 'CONTENT_LENGTH' => 'not-an-int'])->checkBeforePayload());
	}

	public function testInitializeAndNotificationsAllowProtocolNegotiation(): void {
		$guard = $this->guard(['HTTP_MCP_PROTOCOL_VERSION' => 'invalid']);
		$this->assertNull($guard->checkAfterPayload(['method' => 'initialize']));
		$this->assertNull($guard->checkAfterPayload(['method' => 'notifications/initialized']));
		$this->assertNull($guard->checkAfterPayload([['method' => 'initialize'], ['method' => 'notifications/initialized']]));
		$this->assertSame(-32006, $guard->checkAfterPayload([['method' => 'initialize'], ['method' => 'tools/list']])['body']['error']['code']);
	}

	public function testUnsupportedVersionIsRejectedAfterInitialization(): void {
		$guard = $this->guard(['HTTP_MCP_PROTOCOL_VERSION' => '2020-01-01']);
		$error = $guard->checkAfterPayload(['method' => 'tools/list']);
		$this->assertSame(400, $error['status']);
		$this->assertSame(-32006, $error['body']['error']['code']);
		$this->assertNull($this->guard(['HTTP_MCP_PROTOCOL_VERSION' => '2025-06-18'])->checkAfterPayload(['method' => 'tools/list']));
	}

	private function guard(array $server): McpHttpGuard {
		$request = $this->createStub(IRequest::class);
		$request->method('server')->willReturnCallback(static function(string $key, mixed $default = null) use ($server): mixed {
			return $server[$key] ?? '';
		});
		$request->method('getContext')->willReturn(IRequest::CONTEXT_WEB_POST);
		$logger = $this->createStub(ILogger::class);
		return new McpHttpGuard($request, $logger);
	}
}
