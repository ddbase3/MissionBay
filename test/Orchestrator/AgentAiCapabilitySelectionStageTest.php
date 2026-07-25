<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentCapability;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AiResultMetadata;
use MissionBay\Capability\HybridAgentCapabilitySelector;
use MissionBay\Capability\SemanticAgentCapabilitySelector;
use MissionBay\Orchestrator\Stage\AgentAiCapabilitySelectionStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use PHPUnit\Framework\TestCase;

final class AgentAiCapabilitySelectionStageTest extends TestCase {

	public function testStageUsesModelAndPublishesSelectedTools(): void {
		$catalog = new AgentCapabilityCatalog([
			$this->capability('list_ilias_cron_jobs', 'List ILIAS cron jobs.', ['cron', 'list'], 80, 'cron-jobs'),
			$this->capability('list_ilias_plugins', 'List all registered ILIAS plugins.', ['plugins', 'list'], 60, 'plugins'),
			$this->capability('update_webdav', 'Update ILIAS WebDAV settings.', ['webdav'], 70, 'webdav')
		]);
		$model = $this->chatModel('{"selected_tools":["list_ilias_plugins"]}');
		$vars = [
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_MODEL,
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::FAILURE_CODE => '',
			AgentToolLoopContextKeys::CAPABILITY_CATALOG => $catalog,
			AgentToolLoopContextKeys::CAPABILITY_SELECTION_CONFIG => new AgentCapabilitySelectionConfig(
				maxTools: 2,
				selectAllThreshold: 0,
				semanticCandidateTools: 3,
				sticky: false
			),
			AgentToolLoopContextKeys::CAPABILITY_SELECTIONS => [],
			AgentToolLoopContextKeys::SELECTED_TOOL_NAMES => [],
			AgentToolLoopContextKeys::REQUIRED_TOOL_NAMES => [],
			AgentToolLoopContextKeys::EXECUTED_TOOL_CALLS => [],
			AgentToolLoopContextKeys::MESSAGES => [[
				'role' => 'user',
				'content' => 'Welche Plugins habe ich? Keine Cron-Jobs.'
			]],
			AgentToolLoopContextKeys::ITERATION => 1,
			AgentToolLoopContextKeys::MODEL => $model,
			AgentToolLoopContextKeys::MODEL_RESULTS => []
		];
		$context = $this->createMock(IAgentContext::class);
		$context->method('getVar')->willReturnCallback(static fn(string $key): mixed => $vars[$key] ?? null);
		$hybrid = new HybridAgentCapabilitySelector();
		$stage = new AgentAiCapabilitySelectionStage(new SemanticAgentCapabilitySelector($hybrid));

		$result = $stage->process($context);
		$patch = $result->getPatch();

		$this->assertSame('ai-capability-selection', $stage->id());
		$this->assertSame(IAgentStage::AI_USAGE_CONDITIONAL, $stage->getAiUsage());
		$this->assertSame(['list_ilias_plugins'], $patch[AgentToolLoopContextKeys::SELECTED_TOOL_NAMES]);
		$this->assertCount(1, $patch[AgentToolLoopContextKeys::MODEL_RESULTS]);
		$this->assertSame('semantic', $result->getMetadata()['strategy']);
	}

	public function testStageRoutesOnlyTheCurrentUserTurnAndItsObservations(): void {
		$catalog = new AgentCapabilityCatalog([
			$this->capability('list_ilias_plugins', 'List registered plugins.', ['plugins', 'list'], 60, 'plugins'),
			$this->capability('run_ilias_cron_job', 'Run a configured cron job.', ['cron', 'run'], 60, 'cron-jobs')
		]);
		$model = $this->chatModel('{"selected_tools":["run_ilias_cron_job"]}');
		$vars = [
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_MODEL,
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::FAILURE_CODE => '',
			AgentToolLoopContextKeys::CAPABILITY_CATALOG => $catalog,
			AgentToolLoopContextKeys::CAPABILITY_SELECTION_CONFIG => new AgentCapabilitySelectionConfig(
				maxTools: 1,
				selectAllThreshold: 0,
				semanticCandidateTools: 2,
				sticky: false
			),
			AgentToolLoopContextKeys::CAPABILITY_SELECTIONS => [],
			AgentToolLoopContextKeys::SELECTED_TOOL_NAMES => [],
			AgentToolLoopContextKeys::REQUIRED_TOOL_NAMES => [],
			AgentToolLoopContextKeys::EXECUTED_TOOL_CALLS => [],
			AgentToolLoopContextKeys::MESSAGES => [
				['role' => 'system', 'content' => 'Old routing instructions.'],
				['role' => 'user', 'content' => 'List all plugins.'],
				['role' => 'assistant', 'content' => 'The plugins were listed.'],
				['role' => 'user', 'content' => 'Run the scheduled maintenance job.'],
				['role' => 'tool', 'content' => 'The matching job identifier is maintenance-nightly.']
			],
			AgentToolLoopContextKeys::ITERATION => 2,
			AgentToolLoopContextKeys::MODEL => $model,
			AgentToolLoopContextKeys::MODEL_RESULTS => []
		];
		$context = $this->createMock(IAgentContext::class);
		$context->method('getVar')->willReturnCallback(static fn(string $key): mixed => $vars[$key] ?? null);
		$stage = new AgentAiCapabilitySelectionStage(
			new SemanticAgentCapabilitySelector(new HybridAgentCapabilitySelector())
		);

		$stage->process($context);
		$routerPrompt = $model->getMessages()[1]['content'];
		$routerContext = strstr($routerPrompt, "\n\nMaximum selected tools:", true);
		$this->assertTrue(is_string($routerContext));

		$this->assertStringContainsString('user: Run the scheduled maintenance job.', $routerContext);
		$this->assertStringContainsString('tool: The matching job identifier is maintenance-nightly.', $routerContext);
		$this->assertStringNotContainsString('List all plugins.', $routerContext);
		$this->assertStringNotContainsString('Old routing instructions.', $routerContext);
	}


	public function testSourceSelectionPublishesCompleteSourcesAndSelectedMutationNames(): void {
		$catalog = new AgentCapabilityCatalog([
			$this->capability('list_ilias_plugins', 'List registered plugins.', ['plugins', 'list'], 60, 'plugins'),
			$this->capability('set_ilias_plugin_activation_state', 'Change one plugin state.', ['plugins', 'mutation'], 60, 'plugins', true),
			$this->capability('list_ilias_cron_jobs', 'List configured cron jobs.', ['cron', 'list'], 60, 'cron-jobs'),
			$this->capability('run_ilias_cron_job', 'Run one configured cron job.', ['cron', 'mutation'], 60, 'cron-jobs', true)
		]);
		$model = $this->chatModel('{"selected_sources":["plugins","cron-jobs"]}');
		$vars = [
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_MODEL,
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::FAILURE_CODE => '',
			AgentToolLoopContextKeys::CAPABILITY_CATALOG => $catalog,
			AgentToolLoopContextKeys::CAPABILITY_SELECTION_CONFIG => new AgentCapabilitySelectionConfig(
				maxTools: 16,
				selectAllThreshold: 0,
				semanticCandidateTools: 16,
				sticky: true,
				selectionUnit: AgentCapabilitySelectionConfig::SELECTION_UNIT_SOURCE
			),
			AgentToolLoopContextKeys::CAPABILITY_SELECTIONS => [],
			AgentToolLoopContextKeys::SELECTED_TOOL_NAMES => [],
			AgentToolLoopContextKeys::REQUIRED_TOOL_NAMES => [],
			AgentToolLoopContextKeys::EXECUTED_TOOL_CALLS => [],
			AgentToolLoopContextKeys::MESSAGES => [[
				'role' => 'user',
				'content' => 'Disable ReadSpeaker and run Igor2Base.'
			]],
			AgentToolLoopContextKeys::ITERATION => 1,
			AgentToolLoopContextKeys::MODEL => $model,
			AgentToolLoopContextKeys::MODEL_RESULTS => []
		];
		$context = $this->createMock(IAgentContext::class);
		$context->method('getVar')->willReturnCallback(static fn(string $key): mixed => $vars[$key] ?? null);
		$stage = new AgentAiCapabilitySelectionStage(
			new SemanticAgentCapabilitySelector(new HybridAgentCapabilitySelector())
		);

		$patch = $stage->process($context)->getPatch();

		$this->assertSame([
			'list_ilias_plugins',
			'set_ilias_plugin_activation_state',
			'list_ilias_cron_jobs',
			'run_ilias_cron_job'
		], $patch[AgentToolLoopContextKeys::SELECTED_TOOL_NAMES]);
		$this->assertSame([
			'set_ilias_plugin_activation_state',
			'run_ilias_cron_job'
		], $patch[AgentToolLoopContextKeys::MUTATION_TOOL_NAMES]);
	}

	private function capability(
		string $name,
		string $description,
		array $tags,
		int $priority,
		string $sourceId,
		bool $mutation = false
	): AgentCapability {
		return new AgentCapability(
			name: $name,
			title: str_replace('_', ' ', $name),
			description: $description,
			category: $tags[0] ?? '',
			tags: $tags,
			priority: $priority,
			definition: [
				'type' => 'function',
				'readOnlyHint' => !$mutation,
				'mutation' => $mutation,
				'requiresApproval' => $mutation,
				'function' => [
					'name' => $name,
					'description' => $description,
					'parameters' => [
						'type' => 'object',
						'properties' => []
					]
				]
			],
			sourceId: $sourceId,
			sourceName: $sourceId
		);
	}

	private function chatModel(string $content): IAiChatModel {
		return new class($content) implements IAiChatModel {
			private array $messages = [];
			private array $options = [];

			public function __construct(private readonly string $content) {}

			public function complete(array $messages, array $tools = []): AiChatResult {
				$this->messages = $messages;
				return new AiChatResult(
					$this->content,
					[],
					new AiResultMetadata('capability_selection', 'test', 'router')
				);
			}

			public function getMessages(): array { return $this->messages; }
			public function chat(array $messages): string { return $this->content; }
			public function raw(array $messages, array $tools = []): mixed { return $this->content; }
			public function streamResult(array $messages, array $tools, callable $onData, callable $onMeta = null): AiChatResult {
				$onData($this->content);
				return $this->complete($messages, $tools);
			}
			public function stream(array $messages, array $tools, callable $onData, callable $onMeta = null): void { $onData($this->content); }
			public function setOptions(array $options): void { $this->options = $options; }
			public function getOptions(): array { return $this->options; }
		};
	}
}
