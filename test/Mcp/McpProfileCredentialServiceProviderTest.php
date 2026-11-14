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

use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Mcp\McpProfileCredentialServiceProvider;
use Base3\MissionBay\Mcp\McpToolProfileRepository;
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
