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

	private function capability(
		string $name,
		string $description,
		array $tags,
		int $priority,
		string $sourceId
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
			private array $options = [];

			public function __construct(private readonly string $content) {}

			public function complete(array $messages, array $tools = []): AiChatResult {
				return new AiChatResult(
					$this->content,
					[],
					new AiResultMetadata('capability_selection', 'test', 'router')
				);
			}

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
