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

namespace Base3\MissionBay\Test\Service;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentMemory;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MissionBay\Api\IAgentCapabilitySourceMetadata;
use Base3\MissionBay\Api\IAgentComponentPresetRepository;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Api\IAgentContextFactory;
use Base3\MissionBay\Api\IAgentResource;
use Base3\MissionBay\Api\IAgentResourceFactory;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Resource\AbstractAgentResource;
use Base3\MissionBay\Resource\ConfiguredAgentToolResource;
use Base3\MissionBay\Service\AgentComponentPresetMaterializer;
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
