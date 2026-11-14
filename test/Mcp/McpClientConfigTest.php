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

namespace Base3\MissionBay\Test\Mcp;

use InvalidArgumentException;
use Base3\MissionBay\Dto\Mcp\McpClientConfig;
use PHPUnit\Framework\TestCase;

final class McpClientConfigTest extends TestCase {

	public function testDefaultsAndAutoProtocolNegotiation(): void {
		$config = McpClientConfig::fromArray(['endpoint' => ' https://mcp.example.test/endpoint ', 'auth_type' => 'none']);
		$this->assertSame('https://mcp.example.test/endpoint', $config->getEndpoint());
		$this->assertSame([McpClientConfig::PROTOCOL_2025_11_25, McpClientConfig::PROTOCOL_2025_06_18, McpClientConfig::PROTOCOL_2025_03_26], $config->getSupportedProtocolVersions());
		$this->assertSame([], $config->getConnectionHeaders());
		$this->assertTrue($config->shouldVerifyTls());
		$this->assertSame(10, $config->getConnectTimeout());
		$this->assertSame(60, $config->getRequestTimeout());
		$this->assertSame(2097152, $config->getMaxResponseBytes());
		$this->assertSame(20, $config->getMaxPages());
		$this->assertSame(500, $config->getMaxItems());
		$this->assertSame('MissionBay MCP Client', $config->getClientName());
		$this->assertSame('1.0.0', $config->getClientVersion());
	}

	public function testBearerAuthenticationAndRedactionOfConfiguredSecrets(): void {
		$config = McpClientConfig::fromArray([
			'endpoint' => 'https://private.example.test/mcp',
			'auth_type' => 'bearer', 'token' => 'private-token',
			'headers' => ['X-Customer' => 'private-account'],
			'protocol_version' => McpClientConfig::PROTOCOL_2025_06_18,
			'connect_timeout' => '8', 'request_timeout' => 100, 'max_pages' => 3,
			'client_name' => 'Example client', 'client_version' => '2.0.0', 'verify_tls' => 'false'
		]);
		$this->assertSame(['X-Customer' => 'private-account', 'Authorization' => 'Bearer private-token'], $config->getConnectionHeaders());
		$this->assertSame([McpClientConfig::PROTOCOL_2025_06_18], $config->getSupportedProtocolVersions());
		$this->assertFalse($config->shouldVerifyTls());
		$this->assertSame(8, $config->getConnectTimeout());
		$this->assertSame(100, $config->getRequestTimeout());
		$this->assertSame(3, $config->getMaxPages());
		$this->assertSame('Example client', $config->getClientName());
		$this->assertSame('2.0.0', $config->getClientVersion());
		$this->assertSame('[redacted] [redacted] [redacted]', $config->redactSensitiveText('private-token private-account https://private.example.test/mcp'));
		$this->assertSame('private.example.test', $config->toDiagnosticArray()['endpoint_host']);
		$this->assertStringNotContainsString('private-token', json_encode($config->toDiagnosticArray()));
	}

	public function testApiKeyAndBasicAuthorizationHeaders(): void {
		$apiKey = McpClientConfig::fromArray(['endpoint' => 'http://localhost:3000/mcp', 'auth_type' => 'api_key', 'token' => 'secret', 'auth_header_name' => 'X-Client-Key']);
		$this->assertSame(['X-Client-Key' => 'secret'], $apiKey->getConnectionHeaders());
		$basic = McpClientConfig::fromArray(['endpoint' => 'https://example.test/mcp', 'auth_type' => 'basic', 'username' => 'alice', 'token' => 'secret']);
		$this->assertSame('Basic ' . base64_encode('alice:secret'), $basic->getConnectionHeaders()['Authorization']);
		$this->assertStringNotContainsString(base64_encode('alice:secret'), $basic->redactSensitiveText(base64_encode('alice:secret')));
	}

	public function testHmacAuthenticationUsesExplicitSecretOrDerivesKeyHarborSecret(): void {
		$config = McpClientConfig::fromArray(['endpoint' => 'https://example.test/mcp', 'auth_type' => 'hmac', 'token' => 'access', 'hmac_secret' => 'signing']);
		$this->assertTrue($config->usesHmacAuthentication());
		$this->assertSame('signing', $config->getHmacSecret());
		$this->assertSame('Bearer access', $config->getConnectionHeaders()['Authorization']);
		$derived = str_repeat('a', 43);
		$key = 'b3k_' . str_repeat('b', 20) . '_' . $derived;
		$config = McpClientConfig::fromArray(['endpoint' => 'https://example.test/mcp', 'auth_type' => 'hmac', 'token' => $key]);
		$this->assertSame($derived, $config->getHmacSecret());
		$this->assertSame('[redacted]', $config->redactSensitiveText($derived));
	}

	public function testRejectsInvalidEndpointAndAuthentication(): void {
		$invalid = [
			[], ['endpoint' => 'relative/mcp'], ['endpoint' => 'ftp://example.test/mcp'],
			['endpoint' => 'https://alice:secret@example.test/mcp'],
			['endpoint' => 'https://example.test/mcp#fragment'],
			['endpoint' => 'https://example.test/mcp', 'auth_type' => 'unsupported'],
			['endpoint' => 'https://example.test/mcp', 'auth_type' => 'bearer'],
			['endpoint' => 'https://example.test/mcp', 'auth_type' => 'basic', 'token' => 'secret'],
			['endpoint' => 'https://example.test/mcp', 'auth_type' => 'hmac', 'token' => 'access'],
			['endpoint' => 'https://example.test/mcp', 'auth_type' => 'api_key', 'token' => 'secret', 'auth_header_name' => 'Authorization']
		];
		foreach ($invalid as $entry) {
			try {
				McpClientConfig::fromArray($entry);
				$this->fail('Invalid endpoint or authentication configuration was accepted.');
			} catch (InvalidArgumentException) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testRejectsUnsafeOrRepeatedHeaders(): void {
		$base = ['endpoint' => 'https://example.test/mcp', 'auth_type' => 'none'];
		foreach ([
			['Authorization' => 'value'], ['X-Name\n' => 'value'],
			['X-Name' => "one\nsecond"], ['X-Name' => ['nested']],
			['X-Scope' => 'first', 'x-scope' => 'second']
		] as $headers) {
			try {
				McpClientConfig::fromArray($base + ['headers' => $headers]);
				$this->fail('Invalid header was accepted.');
			} catch (InvalidArgumentException) {
				$this->addToAssertionCount(1);
			}
		}
		foreach (['broken', ['X-Name' => "\x01bad"]] as $headers) {
			$this->assertInvalid($base + ['headers' => $headers]);
		}
		$this->assertInvalid(['endpoint' => 'https://example.test/mcp', 'auth_type' => 'api_key', 'token' => 'secret', 'auth_header_name' => 'X-Key', 'headers' => ['x-key' => 'duplicate']]);
	}

	public function testRejectsInvalidProtocolAndResourceLimits(): void {
		$base = ['endpoint' => 'https://example.test/mcp', 'auth_type' => 'none'];
		foreach ([
			['protocol_version' => '2023-01-01'], ['connect_timeout' => 0], ['connect_timeout' => 121],
			['request_timeout' => 601], ['max_response_bytes' => 512], ['max_pages' => -1],
			['max_items' => 100001], ['max_items' => 'invalid']
		] as $override) {
			$this->assertInvalid($base + $override);
		}
	}

	private function assertInvalid(array $settings): void {
		try {
			McpClientConfig::fromArray($settings);
			$this->fail('Invalid MCP configuration was accepted.');
		} catch (InvalidArgumentException) {
			$this->addToAssertionCount(1);
		}
	}
}
