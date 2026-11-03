<?php declare(strict_types=1);

namespace MissionBay\Test\Capability;

use AssistantFoundation\Api\IAgentCapabilityProvider;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentMemory;
use AssistantFoundation\Api\IAgentModule;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Dto\AgentCapabilitySourceConfig;
use AssistantFoundation\Dto\AgentModuleActivation;
use AssistantFoundation\Dto\AgentModuleManifest;
use AssistantFoundation\Dto\AgentStageMount;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AgentStageSlot;
use Base3\Api\IComponent;
use Base3\Api\IComponentResolver;
use MissionBay\Api\IAgentPromptProvider;
use MissionBay\Api\IAgentResourceProvider;
use MissionBay\Api\IAgentTool;
use MissionBay\Capability\AgentCapabilityDiscoveryService;
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
