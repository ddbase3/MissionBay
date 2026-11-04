<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp\Client;

use MissionBay\Api\IMcpClient;
use MissionBay\Api\IMcpTransport;
use MissionBay\Dto\Mcp\McpClientConfig;
use MissionBay\Mcp\Client\McpClientFactory;
use MissionBay\Mcp\Client\McpHmacRequestSigner;
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
