<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp;

use AssistantFoundation\Api\IAgentContext;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentComponentPresetMaterializer;
use MissionBay\Api\IAgentPromptProvider;
use MissionBay\Api\IAgentResourceProvider;
use MissionBay\Api\IAgentTool;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\AgentComponentPresetMaterialization;
use MissionBay\Mcp\McpToolPresetMaterializer;
use MissionBay\Resource\AbstractAgentResource;
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
