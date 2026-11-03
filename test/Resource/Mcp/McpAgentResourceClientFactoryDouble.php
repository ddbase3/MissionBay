<?php declare(strict_types=1);

namespace MissionBay\Test\Resource\Mcp;

use AssistantFoundation\Api\IAgentContext;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IMcpClient;
use MissionBay\Api\IMcpClientFactory;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\Mcp\McpClientConfig;
use MissionBay\Mcp\Client\McpRemoteToolDefinitionMapper;
use MissionBay\Mcp\Client\McpRemoteToolResultMapper;
use MissionBay\Resource\Mcp\McpClientAgentResource;
use PHPUnit\Framework\TestCase;

final class McpAgentResourceClientFactoryDouble implements IMcpClientFactory {

	private ?McpClientConfig $config = null;

	public function __construct(private readonly IMcpClient $client) {}

	public function create(McpClientConfig $config): IMcpClient {
		$this->config = $config;
		return $this->client;
	}

	public function getConfig(): ?McpClientConfig {
		return $this->config;
	}
}
