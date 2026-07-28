<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp\Client;

use MissionBay\Api\IMcpTransport;
use MissionBay\Dto\Mcp\McpClientConfig;
use MissionBay\Dto\Mcp\McpHttpRequest;
use MissionBay\Dto\Mcp\McpHttpResponse;
use MissionBay\Mcp\Client\McpClient;
use MissionBay\Mcp\Client\McpClientException;
use PHPUnit\Framework\TestCase;

final class McpClientTest extends TestCase {

	public function testInitializesSessionAndListsAllToolPages(): void {
		$transport = new McpClientQueueTransport([
			$this->initializeResponse([
				'tools' => ['listChanged' => false],
				'resources' => ['listChanged' => false],
				'prompts' => ['listChanged' => false]
			], 'session-1'),
			new McpHttpResponse(202, [], ''),
			$this->jsonResponse(2, [
				'tools' => [[
					'name' => 'read_file',
					'inputSchema' => ['type' => 'object']
				]],
				'nextCursor' => 'page-2'
			]),
			$this->jsonResponse(3, [
				'tools' => [[
					'name' => 'write_file',
					'inputSchema' => ['type' => 'object']
				]]
			])
		]);
		$client = new McpClient($this->config(), $transport);

		$tools = $client->listTools();

		$this->assertSame(['read_file', 'write_file'], array_column($tools, 'name'));
		$this->assertSame('2025-11-25', $client->getProtocolVersion());
		$this->assertSame('session-1', $client->getSessionId());
		$this->assertCount(4, $transport->getRequests());

		$initialize = $transport->getDecodedRequest(0);
		$this->assertSame('initialize', $initialize['method']);
		$this->assertSame('2025-11-25', $initialize['params']['protocolVersion']);
		$this->assertSame('Bearer top-secret-token', $transport->getRequests()[0]->getHeaders()['Authorization']);
		$this->assertArrayNotHasKey('MCP-Protocol-Version', $transport->getRequests()[0]->getHeaders());
		$this->assertArrayNotHasKey('Mcp-Session-Id', $transport->getRequests()[0]->getHeaders());

		$initialized = $transport->getDecodedRequest(1);
		$this->assertSame('notifications/initialized', $initialized['method']);
		$this->assertSame('2025-11-25', $transport->getRequests()[1]->getHeaders()['MCP-Protocol-Version']);
		$this->assertSame('session-1', $transport->getRequests()[1]->getHeaders()['Mcp-Session-Id']);
		$this->assertSame('identity', $transport->getRequests()[1]->getHeaders()['Accept-Encoding']);

		$pageTwo = $transport->getDecodedRequest(3);
		$this->assertSame('page-2', $pageTwo['params']['cursor']);
	}

	public function testDecodesSseToolResult(): void {
		$transport = new McpClientQueueTransport([
			$this->initializeResponse(['tools' => ['listChanged' => false]]),
			new McpHttpResponse(202, [], ''),
			new McpHttpResponse(
				200,
				['content-type' => 'text/event-stream; charset=utf-8'],
				"event: message\ndata: {\"jsonrpc\":\"2.0\",\"id\":2,\"result\":{\"structuredContent\":{\"ok\":true}}}\n\n"
			)
		]);
		$client = new McpClient($this->config(), $transport);

		$result = $client->callTool('test', ['value' => 7]);

		$this->assertSame(['ok' => true], $result['structuredContent']);
		$call = $transport->getDecodedRequest(2);
		$this->assertSame('tools/call', $call['method']);
		$this->assertSame(['value' => 7], $call['params']['arguments']);
	}

	public function testMissingOptionalResourceTemplatesMethodReturnsEmptyList(): void {
		$transport = new McpClientQueueTransport([
			$this->initializeResponse(['resources' => ['listChanged' => false]]),
			new McpHttpResponse(202, [], ''),
			new McpHttpResponse(200, ['content-type' => 'application/json'], json_encode([
				'jsonrpc' => '2.0',
				'id' => 2,
				'error' => [
					'code' => -32601,
					'message' => 'Method not found'
				]
			], JSON_THROW_ON_ERROR))
		]);
		$client = new McpClient($this->config(), $transport);

		$this->assertSame([], $client->listResourceTemplates());
	}

	public function testNegotiatesAfterHttpJsonRpcVersionError(): void {
		$transport = new McpClientQueueTransport([
			new McpHttpResponse(400, ['content-type' => 'application/json'], json_encode([
				'jsonrpc' => '2.0',
				'id' => 1,
				'error' => [
					'code' => -32602,
					'message' => 'Invalid initialize parameters.'
				]
			], JSON_THROW_ON_ERROR)),
			new McpHttpResponse(200, ['content-type' => 'application/json'], json_encode([
				'jsonrpc' => '2.0',
				'id' => 2,
				'result' => [
					'protocolVersion' => '2025-06-18',
					'capabilities' => ['tools' => ['listChanged' => false]],
					'serverInfo' => ['name' => 'Test MCP', 'version' => '1.0.0']
				]
			], JSON_THROW_ON_ERROR)),
			new McpHttpResponse(202, [], ''),
			$this->jsonResponse(3, ['tools' => []])
		]);
		$client = new McpClient(McpClientConfig::fromArray([
			'endpoint' => 'https://mcp.example.test/mcp',
			'auth_type' => 'none',
			'protocol_version' => 'auto'
		]), $transport);

		$this->assertSame([], $client->listTools());
		$this->assertSame('2025-11-25', $transport->getDecodedRequest(0)['params']['protocolVersion']);
		$this->assertSame('2025-06-18', $transport->getDecodedRequest(1)['params']['protocolVersion']);
	}

	public function testTransportErrorsRedactConfiguredSecrets(): void {
		$transport = new McpClientThrowingTransport(
			new McpClientException(
				'Connection failed for https://mcp.example.test/mcp, top-secret-token and private-header-value.'
			)
		);
		$client = new McpClient($this->config(), $transport);

		try {
			$client->listTools();
			$this->fail('Expected MCP client exception.');
		}
		catch(McpClientException $e) {
			$this->assertStringContainsString('[redacted]', $e->getMessage());
			$this->assertStringNotContainsString('https://mcp.example.test/mcp', $e->getMessage());
			$this->assertStringNotContainsString('top-secret-token', $e->getMessage());
			$this->assertStringNotContainsString('private-header-value', $e->getMessage());
			$this->assertSame(null, $e->getPrevious());
		}
	}

	public function testRecoversOneExpiredHttpSession(): void {
		$transport = new McpClientQueueTransport([
			$this->initializeResponse(['tools' => ['listChanged' => false]], 'session-old', 1),
			new McpHttpResponse(202, [], ''),
			new McpHttpResponse(404, ['content-type' => 'application/json'], '{}'),
			$this->initializeResponse(['tools' => ['listChanged' => false]], 'session-new', 3),
			new McpHttpResponse(202, [], ''),
			$this->jsonResponse(4, [
				'tools' => [[
					'name' => 'read_file',
					'inputSchema' => ['type' => 'object']
				]]
			])
		]);
		$client = new McpClient($this->config(), $transport);

		$tools = $client->listTools();

		$this->assertSame(['read_file'], array_column($tools, 'name'));
		$this->assertSame('session-new', $client->getSessionId());
		$this->assertCount(6, $transport->getRequests());
		$this->assertSame('session-old', $transport->getRequests()[2]->getHeaders()['Mcp-Session-Id']);
		$this->assertArrayNotHasKey('Mcp-Session-Id', $transport->getRequests()[3]->getHeaders());
		$this->assertSame('session-new', $transport->getRequests()[5]->getHeaders()['Mcp-Session-Id']);
	}

	public function testRejectsProtocolControlledApiKeyHeader(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('protocol-controlled header');

		McpClientConfig::fromArray([
			'endpoint' => 'https://mcp.example.test/mcp',
			'auth_type' => 'api_key',
			'token' => 'top-secret-token',
			'auth_header_name' => 'MCP-Protocol-Version'
		]);
	}

	public function testRejectsCaseInsensitiveDuplicateCustomHeaders(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('different casing');

		McpClientConfig::fromArray([
			'endpoint' => 'https://mcp.example.test/mcp',
			'auth_type' => 'none',
			'headers' => [
				'X-Test' => 'first',
				'x-test' => 'second'
			]
		]);
	}

	public function testRejectsApiKeyHeaderDuplicatedAsCustomHeader(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('must not also be configured as a custom header');

		McpClientConfig::fromArray([
			'endpoint' => 'https://mcp.example.test/mcp',
			'auth_type' => 'api_key',
			'token' => 'top-secret-token',
			'auth_header_name' => 'X-API-Key',
			'headers' => [
				'x-api-key' => 'other-value'
			]
		]);
	}

	public function testRejectsEndpointFragments(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('must not contain a URL fragment');

		McpClientConfig::fromArray([
			'endpoint' => 'https://mcp.example.test/mcp#ignored',
			'auth_type' => 'none'
		]);
	}

	public function testRejectsBearerTokensWithHeaderControlCharacters(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('must not contain control characters');

		McpClientConfig::fromArray([
			'endpoint' => 'https://mcp.example.test/mcp',
			'auth_type' => 'bearer',
			'token' => "secret\nvalue"
		]);
	}

	private function config(): McpClientConfig {
		return McpClientConfig::fromArray([
			'endpoint' => 'https://mcp.example.test/mcp',
			'auth_type' => 'bearer',
			'token' => 'top-secret-token',
			'headers' => [
				'X-Private' => 'private-header-value'
			],
			'protocol_version' => '2025-11-25'
		]);
	}

	/** @param array<string,mixed> $capabilities */
	private function initializeResponse(array $capabilities, string $sessionId = '', int $id = 1): McpHttpResponse {
		$headers = ['content-type' => 'application/json'];
		if($sessionId !== '') {
			$headers['mcp-session-id'] = $sessionId;
		}

		return new McpHttpResponse(200, $headers, json_encode([
			'jsonrpc' => '2.0',
			'id' => $id,
			'result' => [
				'protocolVersion' => '2025-11-25',
				'capabilities' => $capabilities,
				'serverInfo' => [
					'name' => 'Test MCP',
					'version' => '1.0.0'
				]
			]
		], JSON_THROW_ON_ERROR));
	}

	/** @param array<string,mixed> $result */
	private function jsonResponse(int $id, array $result): McpHttpResponse {
		return new McpHttpResponse(200, ['content-type' => 'application/json'], json_encode([
			'jsonrpc' => '2.0',
			'id' => $id,
			'result' => $result
		], JSON_THROW_ON_ERROR));
	}
}

final class McpClientQueueTransport implements IMcpTransport {

	/** @var array<int,McpHttpResponse> */
	private array $responses;

	/** @var array<int,McpHttpRequest> */
	private array $requests = [];

	/** @param array<int,McpHttpResponse> $responses */
	public function __construct(array $responses) {
		$this->responses = array_values($responses);
	}

	public function send(McpHttpRequest $request): McpHttpResponse {
		$this->requests[] = $request;
		$response = array_shift($this->responses);

		if(!$response instanceof McpHttpResponse) {
			throw new \RuntimeException('No queued MCP response.');
		}

		return $response;
	}

	/** @return array<int,McpHttpRequest> */
	public function getRequests(): array {
		return $this->requests;
	}

	/** @return array<string,mixed> */
	public function getDecodedRequest(int $index): array {
		return json_decode($this->requests[$index]->getBody(), true, 512, JSON_THROW_ON_ERROR);
	}
}

final class McpClientThrowingTransport implements IMcpTransport {

	public function __construct(private readonly \Throwable $exception) {}

	public function send(McpHttpRequest $request): McpHttpResponse {
		throw $this->exception;
	}
}
