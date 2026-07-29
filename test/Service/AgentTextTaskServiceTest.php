<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentTextTaskRequest;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AiResultMetadata;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentAssistantContextContributionService;
use MissionBay\Api\IAgentComponentPresetMaterializer;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentFlow;
use MissionBay\Api\IAgentFlowCompiler;
use MissionBay\Api\IAgentFlowFactory;
use MissionBay\Api\IAgentNode;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Api\IAgentTool;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\AgentComponentPresetMaterialization;
use MissionBay\Dto\AgentFlowCompilation;
use MissionBay\Profile\AgentContextProfileResolver;
use MissionBay\Profile\AgentToolProfileResolver;
use MissionBay\Service\AgentTextTaskService;
use PHPUnit\Framework\TestCase;

final class AgentTextTaskServiceTest extends TestCase {

	public function testTextTaskUsesConfiguredModelWithoutToolsOrConversationMemory(): void {
		$model = new TextTaskChatModelDouble();
		$store = new TextTaskSettingsStoreDouble();
		$repository = new TextTaskPresetRepositoryDouble();
		$factory = new TextTaskResourceFactoryDouble($model);
		$service = new AgentTextTaskService(
			new TextTaskFlowCompilerDouble(),
			new TextTaskFlowFactoryDouble($model),
			new TextTaskPresetMaterializerDouble(),
			new AgentContextProfileResolver($store, $repository, $factory),
			new AgentToolProfileResolver($store, $repository),
			new TextTaskContextContributionServiceDouble()
		);

		$result = $service->executeTextTask(new AgentTextTaskRequest(
			['agent_runtime' => 'missionbay'],
			'chat-title',
			'Create a concise title.',
			'User: Hello',
			['conversation_id' => 'must-not-leak', 'reference' => '/category/1']
		));

		$this->assertSame('Generated title', $result->getContent());
		$this->assertSame([], $model->getTools());
		$this->assertNull($model->getContext()?->getVar('conversation_id'));
		$this->assertSame('agent-text-task', $model->getContext()?->getVar('source'));
		$this->assertSame('chat-title', $model->getContext()?->getVar('agent_text_task'));
	}

	public function testToolProfileContributesCatalogWithoutExecutableTools(): void {
		$model = new TextTaskChatModelDouble();
		$store = new TextTaskSettingsStoreDouble([
			AgentToolProfileResolver::SETTINGS_GROUP => [
				'admin-tools' => [
					'enabled' => true,
					'internal_enabled' => true,
					'tools' => ['admin-tool']
				]
			]
		]);
		$repository = new TextTaskPresetRepositoryDouble([
			'admin-tool' => [
				'id' => 'admin-tool',
				'enabled' => true,
				'capabilities' => ['tool']
			]
		]);
		$factory = new TextTaskResourceFactoryDouble($model);
		$service = new AgentTextTaskService(
			new TextTaskFlowCompilerDouble(),
			new TextTaskFlowFactoryDouble($model),
			new TextTaskPresetMaterializerDouble(new TextTaskToolDouble()),
			new AgentContextProfileResolver($store, $repository, $factory),
			new AgentToolProfileResolver($store, $repository),
			new TextTaskContextContributionServiceDouble()
		);

		$service->executeTextTask(new AgentTextTaskRequest(
			['tool_profiles' => ['admin-tools']],
			'opening-message',
			'Describe available capabilities only.',
			'Create an opening message.',
			[],
			false,
			true
		));

		$this->assertSame([], $model->getTools());
		$catalogMessages = array_values(array_filter(
			$model->getMessages(),
			static fn(array $message): bool => str_contains((string)($message['content'] ?? ''), 'manage_plugins')
		));
		$this->assertCount(1, $catalogMessages);
	}
}

final class TextTaskFlowCompilerDouble implements IAgentFlowCompiler {

	public function compile(array $agentSettings): AgentFlowCompilation {
		return new AgentFlowCompilation([
			'nodes' => [[
				'id' => 'assistant',
				'type' => 'aiassistantnode',
				'docks' => ['chatmodel' => ['model']]
			]],
			'resources' => [
				[
					'id' => 'model',
					'type' => TextTaskChatModelDouble::getName(),
					'config' => ['temperature' => 0.2]
				],
				[
					'id' => 'unrelated-memory',
					'type' => 'unrelatedmemory'
				]
			]
		]);
	}
}

final class TextTaskFlowFactoryDouble implements IAgentFlowFactory {

	public function __construct(private readonly TextTaskChatModelDouble $model) {}

	public function createFromArray(string $type, array $data, IAgentContext $context): IAgentFlow {
		$definitions = is_array($data['resources'] ?? null) ? $data['resources'] : [];
		if (count($definitions) !== 1 || (string)($definitions[0]['id'] ?? '') !== 'model') {
			throw new \RuntimeException('Text task materialized resources outside the model graph.');
		}
		$this->model->setId('model');
		$this->model->setConfig(is_array($definitions[0]['config'] ?? null) ? $definitions[0]['config'] : []);
		$this->model->init([], $context);

		return new TextTaskFlowDouble(['model' => $this->model], $context);
	}

	public function createEmpty(string $type, ?IAgentContext $context = null): IAgentFlow {
		return new TextTaskFlowDouble([], $context);
	}
}

final class TextTaskFlowDouble implements IAgentFlow {

	public function __construct(
		private array $resources,
		private ?IAgentContext $context = null
	) {}

	public static function getName(): string { return 'texttaskflowdouble'; }
	public function setContext(IAgentContext $context): void { $this->context = $context; }
	public function run(array $inputs): array { return []; }
	public function addNode(IAgentNode $node): void {}
	public function addConnection(string $fromNode, string $fromOutput, string $toNode, string $toInput): void {}
	public function addInitialInput(string $nodeId, string $key, mixed $value): void {}
	public function getInitialInputs(): array { return []; }
	public function getConnections(): array { return []; }
	public function getNextNode(string $currentNodeId, array $output): ?string { return null; }
	public function mapInputs(string $fromNodeId, string $toNodeId, array $output): array { return []; }
	public function isReady(string $nodeId, array $currentInputs): bool { return false; }
	public function addResource(IAgentResource $resource): void { $this->resources[$resource->getId()] = $resource; }
	public function getResources(): array { return $this->resources; }
	public function addDockConnection(string $nodeId, string $dockName, string $resourceId): void {}
	public function getAllDockConnections(): array { return []; }
	public function getDockConnections(string $nodeId): array { return []; }
}

final class TextTaskResourceFactoryDouble implements IAgentResourceFactory {

	public function __construct(private readonly TextTaskChatModelDouble $model) {}

	public function createResource(string $type): ?IAgentResource {
		return $type === TextTaskChatModelDouble::getName() ? $this->model : null;
	}
}

final class TextTaskPresetMaterializerDouble implements IAgentComponentPresetMaterializer {

	public function __construct(private readonly ?IAgentTool $tool = null) {}

	public function createContext(array $vars = []): IAgentContext {
		return new AgentContext(null, $vars);
	}

	public function materialize(string $presetId, IAgentContext $context): AgentComponentPresetMaterialization {
		if (!$this->tool instanceof IAgentTool || $presetId !== 'admin-tool') {
			throw new \LogicException('Unexpected text-task profile materialization: ' . $presetId);
		}

		return new AgentComponentPresetMaterialization(
			$presetId,
			['id' => $presetId, 'capabilities' => ['tool']],
			null,
			$this->tool,
			null,
			null,
			['tool']
		);
	}
}

final class TextTaskChatModelDouble implements IAgentResource, IAiChatModel {

	private string $id = '';
	private array $config = [];
	private ?IAgentContext $context = null;
	private array $tools = [];
	private array $messages = [];

	public static function getName(): string {
		return 'texttaskchatmodeldouble';
	}

	public function getId(): string {
		return $this->id;
	}

	public function setId(string $id): void {
		$this->id = $id;
	}

	public function getDescription(): string {
		return 'Text-task model test double.';
	}

	public function getDockDefinitions(): array {
		return [];
	}

	public function getConfig(): array {
		return $this->config;
	}

	public function setConfig(array $config): void {
		$this->config = $config;
	}

	public function init(array $resources, IAgentContext $context): void {
		$this->context = $context;
	}

	public function complete(array $messages, array $tools = []): AiChatResult {
		$this->messages = $messages;
		$this->tools = $tools;
		return new AiChatResult(
			'Generated title',
			[],
			new AiResultMetadata('chat', 'test', 'test-model')
		);
	}

	public function chat(array $messages): string {
		return 'Generated title';
	}

	public function raw(array $messages, array $tools = []): mixed {
		return [];
	}

	public function streamResult(
		array $messages,
		array $tools,
		callable $onData,
		callable $onMeta = null
	): AiChatResult {
		return $this->complete($messages, $tools);
	}

	public function stream(array $messages, array $tools, callable $onData, callable $onMeta = null): void {
	}

	public function setOptions(array $options): void {
	}

	public function getOptions(): array {
		return [];
	}

	public function getContext(): ?IAgentContext {
		return $this->context;
	}

	public function getTools(): array {
		return $this->tools;
	}

	public function getMessages(): array {
		return $this->messages;
	}
}

final class TextTaskToolDouble implements IAgentTool {

	public static function getName(): string {
		return 'texttasktooldouble';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'function' => [
				'name' => 'manage_plugins',
				'description' => 'Manages configured plugins.',
				'parameters' => ['type' => 'object', 'properties' => []]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		throw new \LogicException('Text-task capability catalog must never execute tools.');
	}
}

final class TextTaskSettingsStoreDouble implements ISettingsStore {
	public function __construct(private array $groups = []) {}
	public function get(string $group, string $name, array $default = []): array { return $this->groups[$group][$name] ?? $default; }
	public function set(string $group, string $name, array $settings): void { $this->groups[$group][$name] = $settings; }
	public function has(string $group, string $name): bool { return isset($this->groups[$group][$name]); }
	public function remove(string $group, string $name): void { unset($this->groups[$group][$name]); }
	public function getGroup(string $group): array { return $this->groups[$group] ?? []; }
	public function save(): void {}
	public function reload(): void {}
}

final class TextTaskPresetRepositoryDouble implements IAgentComponentPresetRepository {
	public function __construct(private array $presets = []) {}
	public function getPresets(): array { return $this->presets; }
	public function getPreset(string $id, array $default = []): array { return $this->presets[$id] ?? $default; }
	public function hasPreset(string $id): bool { return isset($this->presets[$id]); }
	public function savePreset(string $id, array $preset): void { $this->presets[$id] = $preset; }
	public function removePreset(string $id): void { unset($this->presets[$id]); }
}

final class TextTaskContextContributionServiceDouble implements IAgentAssistantContextContributionService {
	public function buildMessages(array $resources, IAgentContext $context, ?\Base3\Logger\Api\ILogger $logger = null): array {
		return [];
	}
}
