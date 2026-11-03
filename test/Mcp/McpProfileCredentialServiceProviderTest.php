<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp;

use Base3\Settings\Api\ISettingsStore;
use MissionBay\Mcp\McpProfileCredentialServiceProvider;
use MissionBay\Mcp\McpToolProfileRepository;
use PHPUnit\Framework\TestCase;

final class McpProfileCredentialServiceProviderTest extends TestCase {

	public function testPublishesOnlyEnabledCredentialBackedMcpProfiles(): void {
		$repository = new McpToolProfileRepository(new CredentialProfileSettingsStore([
			'tool-profile' => [
				'admin' => [
					'label' => 'Administration',
					'description' => 'Administrative MCP tools.',
					'enabled' => true,
					'mcp_enabled' => true,
					'mcp_credential_enabled' => true,
					'tools' => ['admin-tools']
				],
				'fixed-only' => [
					'enabled' => true,
					'mcp_enabled' => true,
					'mcp_fixed_bearer_enabled' => true,
					'token' => 'fixed-token',
					'tools' => ['read-tools']
				],
				'disabled' => [
					'enabled' => false,
					'mcp_enabled' => true,
					'mcp_credential_enabled' => true,
					'tools' => ['admin-tools']
				]
			]
		]));
		$provider = new McpProfileCredentialServiceProvider($repository);

		$services = $provider->getServices();

		self::assertCount(1, $services);
		self::assertSame('missionbay:mcp:admin', $services[0]->getServiceId());
		self::assertSame('MissionBay MCP - Administration', $services[0]->getLabel());
		self::assertSame('Administrative MCP tools.', $services[0]->getDescription());
	}
}

require_once __DIR__ . '/CredentialProfileSettingsStore.php';
