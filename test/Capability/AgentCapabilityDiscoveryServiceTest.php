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

final class DiscoveryComponentResolver implements IComponentResolver {

	/** @param array<string,array<string,IComponent>> $components */
	public function __construct(private array $components) {}

	public function has(string $interfaceName, string $id): bool {
		return isset($this->components[$interfaceName][$id]);
	}

	public function get(string $interfaceName, string $id): ?IComponent {
		return $this->components[$interfaceName][$id] ?? null;
	}

	public function all(string $interfaceName): iterable {
		return array_values($this->components[$interfaceName] ?? []);
	}
}

final class DiscoveryTool implements IAgentTool, IComponent {

	public function __construct(private string $id, private string $toolName) {}
	public static function getName(): string { return 'discoverytool'; }
	public function id(): string { return $this->id; }
	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'function' => [
				'name' => $this->toolName,
				'description' => 'Test tool.',
				'parameters' => ['type' => 'object', 'properties' => []]
			]
		]];
	}
	public function callTool(string $name, array $arguments, IAgentContext $context): mixed { return ['ok' => true]; }
}

final class DiscoveryResourceProvider implements IAgentResourceProvider, IComponent {

	public function __construct(private string $id) {}
	public static function getName(): string { return 'discoveryresourceprovider'; }
	public function id(): string { return $this->id; }
	public function getResourceDefinitions(IAgentContext $context): array { return []; }
	public function readResource(string $uri, IAgentContext $context): ?array { return null; }
}

final class DiscoveryPromptProvider implements IAgentPromptProvider, IComponent {

	public function __construct(private string $id) {}
	public static function getName(): string { return 'discoverypromptprovider'; }
	public function id(): string { return $this->id; }
	public function getPromptDefinitions(IAgentContext $context): array { return []; }
	public function getPrompt(string $name, array $arguments, IAgentContext $context): ?array { return null; }
}

final class DiscoveryCapabilityProvider implements IAgentCapabilityProvider {

	/** @param array<int,IAgentTool> $tools @param array<int,IAgentResourceProvider> $resources @param array<int,IAgentPromptProvider> $prompts */
	public function __construct(private string $id, private array $toolValues, private array $resources, private array $prompts) {}
	public static function getName(): string { return 'discoverycapabilityprovider'; }
	public function id(): string { return $this->id; }
	public function name(): string { return $this->id; }
	public function tools(IAgentContext $context): iterable { return $this->toolValues; }
	public function resourceProviders(IAgentContext $context): iterable { return $this->resources; }
	public function promptProviders(IAgentContext $context): iterable { return $this->prompts; }
}

final class DiscoveryModule implements IAgentModule {

	public function __construct(private string $id, private AgentModuleActivation $activation) {}
	public static function getName(): string { return 'discoverymodule'; }
	public function id(): string { return $this->id; }
	public function manifest(): AgentModuleManifest { return new AgentModuleManifest($this->id, 'Coding Style', 'Test module.'); }
	public function activate(IAgentContext $context): AgentModuleActivation { return $this->activation; }
}

final class DiscoveryStage implements IAgentStage {

	public function __construct(private string $id) {}
	public static function getName(): string { return 'discoverystage'; }
	public function id(): string { return $this->id; }
	public function name(): string { return $this->id; }
	public function getDescription(): string { return 'Test module stage.'; }
	public function getAiUsage(): string { return IAgentStage::AI_USAGE_NONE; }
	public function supports(IAgentContext $context): bool { return false; }
	public function process(IAgentContext $context): AgentStageResult { return AgentStageResult::none(); }
}

final class DiscoveryContext implements IAgentContext {

	private array $vars = [];
	private IAgentMemory $memory;

	public function __construct() {
		$this->memory = new DiscoveryMemory();
	}
	public static function getName(): string { return 'discoverycontext'; }
	public function getMemory(): IAgentMemory { return $this->memory; }
	public function setMemory(IAgentMemory $memory): void { $this->memory = $memory; }
	public function setVar(string $key, mixed $value): void { $this->vars[$key] = $value; }
	public function getVar(string $key): mixed { return $this->vars[$key] ?? null; }
	public function forgetVar(string $key): void { unset($this->vars[$key]); }
	public function listVars(): array { return array_keys($this->vars); }
}

final class DiscoveryMemory implements IAgentMemory {
	public static function getName(): string { return 'discoverymemory'; }
	public function loadNodeHistory(string $nodeId): array { return []; }
	public function appendNodeHistory(string $nodeId, array $message): void {}
	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool { return false; }
	public function resetNodeHistory(string $nodeId): void {}
	public function getPriority(): int { return 0; }
}
