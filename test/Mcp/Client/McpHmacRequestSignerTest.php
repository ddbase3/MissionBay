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
use Base3\MissionBay\Dto\Mcp\McpClientConfig;
use Base3\MissionBay\Dto\Mcp\McpHttpRequest;
use Base3\MissionBay\Dto\Mcp\McpHttpResponse;
use Base3\MissionBay\Mcp\Client\McpClient;
use Base3\MissionBay\Mcp\Client\McpHmacRequestSigner;
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

require_once __DIR__ . '/HmacRecordingTransport.php';
