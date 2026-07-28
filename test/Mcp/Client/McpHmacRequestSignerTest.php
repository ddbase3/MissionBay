<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp\Client;

use MissionBay\Api\IMcpTransport;
use MissionBay\Dto\Mcp\McpClientConfig;
use MissionBay\Dto\Mcp\McpHttpRequest;
use MissionBay\Dto\Mcp\McpHttpResponse;
use MissionBay\Mcp\Client\McpClient;
use MissionBay\Mcp\Client\McpHmacRequestSigner;
use PHPUnit\Framework\TestCase;

final class McpHmacRequestSignerTest extends TestCase {

	public function testBuildsKeyHarborCanonicalSignature(): void {
		$config = McpClientConfig::fromArray([
			'endpoint' => 'https://example.test/mcp.php?profile=admin',
			'auth_type' => 'hmac',
			'token' => 'token-value',
			'hmac_secret' => 'secret-value'
		]);
		$signer = new McpHmacRequestSigner();
		$body = '{"jsonrpc":"2.0","id":1,"method":"initialize"}';

		$headers = $signer->createHeaders($config, 'POST', $body, 1785140000, 'test-nonce');
		$canonical = implode("\n", [
			'POST',
			'/mcp.php',
			'profile=admin',
			'1785140000',
			'test-nonce',
			hash('sha256', $body)
		]);

		self::assertSame('1785140000', $headers['X-BASE3-Timestamp']);
		self::assertSame('test-nonce', $headers['X-BASE3-Nonce']);
		self::assertSame(
			hash_hmac('sha256', $canonical, 'secret-value'),
			$headers['X-BASE3-Signature']
		);
	}

	public function testDerivesKeyHarborSecretAndSignsEveryClientRequest(): void {
		$secret = str_repeat('A', 43);
		$config = McpClientConfig::fromArray([
			'endpoint' => 'https://example.test/mcp.php?profile=admin',
			'auth_type' => 'hmac',
			'token' => 'b3k_' . str_repeat('a', 20) . '_' . $secret,
			'protocol_version' => '2025-11-25'
		]);
		$transport = new HmacRecordingTransport();
		$client = new McpClient($config, $transport, new McpHmacRequestSigner());

		$client->listTools();

		self::assertCount(3, $transport->requests);
		foreach($transport->requests as $request) {
			$headers = $request->getHeaders();
			self::assertSame('Bearer b3k_' . str_repeat('a', 20) . '_' . $secret, $headers['Authorization']);
			self::assertArrayHasKey('X-BASE3-Timestamp', $headers);
			self::assertArrayHasKey('X-BASE3-Nonce', $headers);
			self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $headers['X-BASE3-Signature']);
		}
	}
}

final class HmacRecordingTransport implements IMcpTransport {

	/** @var array<int,McpHttpRequest> */
	public array $requests = [];

	public function send(McpHttpRequest $request): McpHttpResponse {
		$this->requests[] = $request;
		$payload = json_decode($request->getBody(), true, 512, JSON_THROW_ON_ERROR);
		$method = (string)($payload['method'] ?? '');

		if($method === 'initialize') {
			return new McpHttpResponse(200, ['content-type' => 'application/json'], json_encode([
				'jsonrpc' => '2.0',
				'id' => $payload['id'],
				'result' => [
					'protocolVersion' => '2025-11-25',
					'capabilities' => ['tools' => ['listChanged' => false]],
					'serverInfo' => ['name' => 'Test', 'version' => '1.0.0']
				]
			], JSON_THROW_ON_ERROR));
		}

		if($method === 'notifications/initialized') {
			return new McpHttpResponse(202, [], '');
		}

		return new McpHttpResponse(200, ['content-type' => 'application/json'], json_encode([
			'jsonrpc' => '2.0',
			'id' => $payload['id'],
			'result' => ['tools' => []]
		], JSON_THROW_ON_ERROR));
	}
}
