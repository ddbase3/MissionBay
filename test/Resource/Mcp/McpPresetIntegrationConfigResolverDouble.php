<?php declare(strict_types=1);

namespace MissionBay\Test\Resource\Mcp;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentMemory;
use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Api\IMcpClient;
use MissionBay\Api\IMcpClientFactory;
use MissionBay\Capability\AgentCapabilityCatalogBuilder;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\Mcp\McpClientConfig;
use MissionBay\Mcp\Client\McpRemoteToolDefinitionMapper;
use MissionBay\Mcp\Client\McpRemoteToolResultMapper;
use MissionBay\Profile\AgentAssistantToolSetupFactory;
use MissionBay\Resource\ConfiguredAgentToolResource;
use MissionBay\Resource\Mcp\McpClientAgentResource;
use MissionBay\Service\AgentComponentPresetMaterializer;
use PHPUnit\Framework\TestCase;

final class McpPresetIntegrationConfigResolverDouble implements IAgentConfigValueResolver {

	public function resolveValue(array|string|int|float|bool|null $config): mixed {
		return $config;
	}
}
