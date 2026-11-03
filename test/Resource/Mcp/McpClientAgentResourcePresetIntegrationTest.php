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

final class McpClientAgentResourcePresetIntegrationTest extends TestCase {

	public function testPresetIdIsAppliedByMissionBayAndRemoteNameIsPreservedForExecution(): void {
		$client = new McpPresetIntegrationClientDouble();
		$materializer = new AgentComponentPresetMaterializer(
			new McpPresetIntegrationRepositoryDouble(),
			new McpPresetIntegrationResourceFactoryDouble($client),
			new McpPresetIntegrationContextFactoryDouble(),
			new McpPresetIntegrationNullLogger()
		);
		$context = new AgentContext();
		$tool = $materializer->materialize('deepwiki', $context)->getTool();

		$this->assertInstanceOf(ConfiguredAgentToolResource::class, $tool);

		$setup = (new AgentAssistantToolSetupFactory(new AgentCapabilityCatalogBuilder()))->create(
			[$tool],
			null,
			'Show the repository documentation structure.',
			'',
			$context
		);
		$definitions = $setup->getToolDefs();

		$this->assertCount(1, $definitions);
		$this->assertSame(
			'deepwiki__read_wiki_structure',
			$definitions[0]['function']['name'] ?? null
		);
		$this->assertTrue($definitions[0]['requiresApproval'] ?? false);

		$result = $tool->callTool(
			'deepwiki__read_wiki_structure',
			['repoName' => 'modelcontextprotocol/servers'],
			$context
		);

		$this->assertSame('read_wiki_structure', $client->getLastToolName());
		$this->assertSame('modelcontextprotocol/servers', $client->getLastArguments()['repoName'] ?? null);
		$this->assertSame('# Repository Wiki Structure', $result);
	}
}

require_once __DIR__ . '/McpPresetIntegrationRepositoryDouble.php';
require_once __DIR__ . '/McpPresetIntegrationResourceFactoryDouble.php';
require_once __DIR__ . '/McpPresetIntegrationContextFactoryDouble.php';
require_once __DIR__ . '/McpPresetIntegrationConfigResolverDouble.php';
require_once __DIR__ . '/McpPresetIntegrationClientFactoryDouble.php';
require_once __DIR__ . '/McpPresetIntegrationClientDouble.php';
require_once __DIR__ . '/McpPresetIntegrationEventManagerDouble.php';
require_once __DIR__ . '/McpPresetIntegrationNullLogger.php';
