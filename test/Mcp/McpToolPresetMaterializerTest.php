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

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MissionBay\Api\IAgentComponentPresetMaterializer;
use Base3\MissionBay\Api\IAgentPromptProvider;
use Base3\MissionBay\Api\IAgentResourceProvider;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Dto\AgentComponentPresetMaterialization;
use Base3\MissionBay\Mcp\McpToolPresetMaterializer;
use Base3\MissionBay\Resource\AbstractAgentResource;
use PHPUnit\Framework\TestCase;

final class McpToolPresetMaterializerTest extends TestCase {

	public function testMaterializesToolsResourcesAndPromptsFromOnePresetInstance(): void {
		$resource = new McpPresetCapabilityResource('remote-preset');
		$presetMaterializer = new McpPresetMaterializerDouble($resource);
		$materializer = new McpToolPresetMaterializer(
			$presetMaterializer,
			new McpPresetMaterializerNullLogger()
		);
		$context = new AgentContext();

		$capabilities = $materializer->materializeCapabilities([
			'tools' => ['remote-preset', 'remote-preset']
		], $context);

		$this->assertCount(1, $capabilities['tools']);
		$this->assertCount(1, $capabilities['resourceProviders']);
		$this->assertCount(1, $capabilities['promptProviders']);
		$this->assertSame($resource, $capabilities['tools'][0]);
		$this->assertSame($resource, $capabilities['resourceProviders'][0]);
		$this->assertSame($resource, $capabilities['promptProviders'][0]);
		$this->assertSame(1, $presetMaterializer->getMaterializeCalls());
		$this->assertSame([], $materializer->getWarnings());
		$this->assertSame([$resource], $materializer->materialize([
			'tools' => ['remote-preset']
		], $context));
	}
}

require_once __DIR__ . '/McpPresetMaterializerDouble.php';
require_once __DIR__ . '/McpPresetCapabilityResource.php';
require_once __DIR__ . '/McpPresetMaterializerNullLogger.php';
