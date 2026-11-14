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

namespace Base3\MissionBay\Test\Capability;

use Base3\AssistantFoundation\Api\IAgentCapabilityProvider;
use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentMemory;
use Base3\AssistantFoundation\Api\IAgentModule;
use Base3\AssistantFoundation\Api\IAgentStage;
use Base3\AssistantFoundation\Dto\AgentCapabilitySourceConfig;
use Base3\AssistantFoundation\Dto\AgentModuleActivation;
use Base3\AssistantFoundation\Dto\AgentModuleManifest;
use Base3\AssistantFoundation\Dto\AgentStageMount;
use Base3\AssistantFoundation\Dto\AgentStageResult;
use Base3\AssistantFoundation\Dto\AgentStageSlot;
use Base3\CoreFoundation\Api\IComponent;
use Base3\CoreFoundation\Api\IComponentResolver;
use Base3\MissionBay\Api\IAgentPromptProvider;
use Base3\MissionBay\Api\IAgentResourceProvider;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Capability\AgentCapabilityDiscoveryService;
use PHPUnit\Framework\TestCase;

final class AgentCapabilityDiscoveryServiceTest extends TestCase {

	public function testExplicitSourcesProvidersAndModulesBuildRunLocalComposition(): void {
		$directTool = new DiscoveryTool('direct-tool', 'direct_call');
		$providerTool = new DiscoveryTool('provider-tool', 'provider_call');
		$moduleTool = new DiscoveryTool('module-tool', 'module_call');
		$resourceProvider = new DiscoveryResourceProvider('project-files');
		$promptProvider = new DiscoveryPromptProvider('support-prompts');
		$provider = new DiscoveryCapabilityProvider('internal-mcp', [$providerTool], [$resourceProvider], [$promptProvider]);
		$moduleStage = new DiscoveryStage('module-before-tool');
		$module = new DiscoveryModule('coding-style', new AgentModuleActivation(
			instructions: ['Follow the configured coding style.'],
			tools: [$moduleTool],
			resourceProviders: [$resourceProvider],
			promptProviders: [$promptProvider],
			stages: [new AgentStageMount(AgentStageSlot::BEFORE_TOOL_CALL, $moduleStage, 10)]
		));
		$resolver = new DiscoveryComponentResolver([
			IAgentTool::class => ['direct-tool' => $directTool],
			IAgentCapabilityProvider::class => ['internal-mcp' => $provider],
			IAgentModule::class => ['coding-style' => $module],
			IAgentResourceProvider::class => ['project-files' => $resourceProvider],
			IAgentPromptProvider::class => ['support-prompts' => $promptProvider]
		]);
		$service = new AgentCapabilityDiscoveryService($resolver);

		$result = $service->discover([], AgentCapabilitySourceConfig::fromArray([
			'tools' => ['direct-tool'],
			'providers' => ['internal-mcp'],
			'modules' => ['coding-style'],
			'resourceProviders' => ['project-files'],
			'promptProviders' => ['support-prompts']
		]), new DiscoveryContext());

		$this->assertFalse($result->hasErrors());
		$this->assertSame([$directTool, $providerTool, $moduleTool], $result->getTools());
		$this->assertSame([$resourceProvider], $result->getResourceProviders());
		$this->assertSame([$promptProvider], $result->getPromptProviders());
		$this->assertSame(['Follow the configured coding style.'], $result->getInstructions());
		$this->assertCount(1, $result->getStageMounts());
		$this->assertSame('module-before-tool', $result->getStageMounts()[0]->getStage()->id());
		$this->assertSame([
			'tools' => ['direct-tool'],
			'providers' => ['internal-mcp'],
			'modules' => ['coding-style'],
			'resourceProviders' => ['project-files'],
			'promptProviders' => ['support-prompts']
		], $result->toArray()['resolved']);
	}

	public function testStrictMissingSourceIsAnErrorAndNonStrictSourceIsAWarning(): void {
		$service = new AgentCapabilityDiscoveryService(new DiscoveryComponentResolver([]));

		$strict = $service->discover([], AgentCapabilitySourceConfig::fromArray([
			'tools' => ['missing'],
			'strict' => true
		]), new DiscoveryContext());
		$lenient = $service->discover([], AgentCapabilitySourceConfig::fromArray([
			'tools' => ['missing'],
			'strict' => false
		]), new DiscoveryContext());

		$this->assertTrue($strict->hasErrors());
		$this->assertNotEmpty($strict->getErrors());
		$this->assertFalse($lenient->hasErrors());
		$this->assertNotEmpty($lenient->getWarnings());
	}
}

require_once __DIR__ . '/DiscoveryComponentResolver.php';
require_once __DIR__ . '/DiscoveryTool.php';
require_once __DIR__ . '/DiscoveryResourceProvider.php';
require_once __DIR__ . '/DiscoveryPromptProvider.php';
require_once __DIR__ . '/DiscoveryCapabilityProvider.php';
require_once __DIR__ . '/DiscoveryModule.php';
require_once __DIR__ . '/DiscoveryStage.php';
require_once __DIR__ . '/DiscoveryContext.php';
require_once __DIR__ . '/DiscoveryMemory.php';
