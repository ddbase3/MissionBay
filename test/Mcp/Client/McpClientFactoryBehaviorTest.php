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

use Base3\MissionBay\Api\IMcpClient;
use Base3\MissionBay\Api\IMcpTransport;
use Base3\MissionBay\Dto\Mcp\McpClientConfig;
use Base3\MissionBay\Mcp\Client\McpClientFactory;
use Base3\MissionBay\Mcp\Client\McpHmacRequestSigner;
use PHPUnit\Framework\TestCase;

final class McpClientFactoryBehaviorTest extends TestCase {

	public function testFactoryCreatesDistinctRunLocalClients(): void {
		$config = McpClientConfig::fromArray(['endpoint' => 'https://mcp.example.org/api', 'auth_type' => 'none']);
		$factory = new McpClientFactory($this->createStub(IMcpTransport::class), new McpHmacRequestSigner());
		$first = $factory->create($config);
		$second = $factory->create($config);
		$this->assertInstanceOf(IMcpClient::class, $first);
		$this->assertInstanceOf(IMcpClient::class, $second);
		$this->assertNotSame($first, $second);
	}

	public function testFactoryNameRemainsDiscoverable(): void {
		$this->assertSame('mcpclientfactory', McpClientFactory::getName());
	}
}
