<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentMemory;
use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentCapabilitySourceMetadata;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Api\IAgentTool;
use MissionBay\Context\AgentContext;
use MissionBay\Resource\AbstractAgentResource;
use MissionBay\Resource\ConfiguredAgentToolResource;
use MissionBay\Service\AgentComponentPresetMaterializer;
use PHPUnit\Framework\TestCase;

final class AgentComponentPresetMaterializerToolPrefixTest extends TestCase {

	public function testPresetIdPrefixesDefinitionsAndIsRemovedBeforeExecution(): void {
		$remoteTool = new MaterializerRemoteToolDouble();
		$materializer = new AgentComponentPresetMaterializer(
			new MaterializerPresetRepositoryDouble(),
			new MaterializerResourceFactoryDouble($remoteTool),
			new MaterializerContextFactoryDouble(),
			new MaterializerNullLogger()
		);
		$context = new AgentContext();
		$materialization = $materializer->materialize('deepwiki', $context);
		$tool = $materialization->getTool();

		$this->assertInstanceOf(ConfiguredAgentToolResource::class, $tool);
		$this->assertSame(
			'deepwiki__read_wiki_structure',
			$tool->getToolDefinitions()[0]['function']['name'] ?? null
		);
		$this->assertInstanceOf(IAgentCapabilitySourceMetadata::class, $tool);
		$this->assertSame('deepwiki', $tool->getCapabilitySourceId());
		$this->assertSame('DeepWiki', $tool->getCapabilitySourceLabel());
		$this->assertSame(
			'Remote MCP-like tool used to verify configured preset naming.',
			$tool->getCapabilitySourceDescription()
		);

		$result = $tool->callTool(
			'deepwiki__read_wiki_structure',
			['repoName' => 'modelcontextprotocol/servers'],
			$context
		);

		$this->assertSame('read_wiki_structure', $remoteTool->getLastName());
		$this->assertSame(['ok' => true], $result);
	}
}

require_once __DIR__ . '/MaterializerPresetRepositoryDouble.php';
require_once __DIR__ . '/MaterializerResourceFactoryDouble.php';
require_once __DIR__ . '/MaterializerContextFactoryDouble.php';
require_once __DIR__ . '/MaterializerIdentityResolverDouble.php';
require_once __DIR__ . '/MaterializerEventManagerDouble.php';
require_once __DIR__ . '/MaterializerRemoteToolDouble.php';
require_once __DIR__ . '/MaterializerNullLogger.php';
