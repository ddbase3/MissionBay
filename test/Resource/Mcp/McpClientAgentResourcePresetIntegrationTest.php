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

namespace Base3\MissionBay\Test\Resource\Mcp;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentMemory;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MissionBay\Api\IAgentComponentPresetRepository;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Api\IAgentContextFactory;
use Base3\MissionBay\Api\IAgentResource;
use Base3\MissionBay\Api\IAgentResourceFactory;
use Base3\MissionBay\Api\IMcpClient;
use Base3\MissionBay\Api\IMcpClientFactory;
use Base3\MissionBay\Capability\AgentCapabilityCatalogBuilder;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Dto\Mcp\McpClientConfig;
use Base3\MissionBay\Mcp\Client\McpRemoteToolDefinitionMapper;
use Base3\MissionBay\Mcp\Client\McpRemoteToolResultMapper;
use Base3\MissionBay\Profile\AgentAssistantToolSetupFactory;
use Base3\MissionBay\Resource\ConfiguredAgentToolResource;
use Base3\MissionBay\Resource\Mcp\McpClientAgentResource;
use Base3\MissionBay\Service\AgentComponentPresetMaterializer;
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
