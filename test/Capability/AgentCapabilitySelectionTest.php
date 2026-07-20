<?php declare(strict_types=1);

namespace MissionBay\Test\Capability;

use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentCapability;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use AssistantFoundation\Dto\AgentCapabilitySelectionRequest;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AiResultMetadata;
use MissionBay\Capability\HybridAgentCapabilitySelector;
use MissionBay\Capability\ProfileAwareAgentCapabilitySelector;
use MissionBay\Capability\SemanticAgentCapabilitySelector;
use PHPUnit\Framework\TestCase;

final class AgentCapabilitySelectionTest extends TestCase {

	public function testLargeCatalogIsReducedByContextAndLimit(): void {
		$capabilities = [];
		for ($index = 1; $index <= 20; $index++) {
			$capabilities[] = $this->capability(
				'tool_' . $index,
				'Generic tool ' . $index,
				['generic'],
				0
			);
		}
		$capabilities[] = $this->capability(
			'weather_forecast',
			'Read the current weather forecast for a city.',
			['weather', 'forecast'],
			5
		);

		$selection = (new HybridAgentCapabilitySelector())->select(
			new AgentCapabilityCatalog($capabilities),
			new AgentCapabilitySelectionRequest(
				iteration: 1,
				contextText: 'What will the weather forecast be in Berlin tomorrow?',
				config: new AgentCapabilitySelectionConfig(maxTools: 4, selectAllThreshold: 4)
			)
		);

		$this->assertCount(4, $selection->getCapabilities());
		$this->assertContains('weather_forecast', $selection->getToolNames());
	}

	public function testRequiredAndAlwaysAvailableToolsSurviveRanking(): void {
		$catalog = new AgentCapabilityCatalog([
			$this->capability('general_info', 'General diagnostics.', ['info'], 0),
			$this->capability('crm_write', 'Updates a CRM record.', ['crm'], -10),
			$this->capability('weather', 'Weather information.', ['weather'], 50),
			$this->capability('search', 'General search.', ['search'], 40)
		]);

		$selection = (new HybridAgentCapabilitySelector())->select(
			$catalog,
			new AgentCapabilitySelectionRequest(
				iteration: 2,
				contextText: 'Find the weather.',
				config: new AgentCapabilitySelectionConfig(
					maxTools: 2,
					selectAllThreshold: 0,
					alwaysAvailable: ['general_info']
				),
				requiredToolNames: ['crm_write']
			)
		);

		$this->assertSame(['general_info', 'crm_write'], $selection->getToolNames());
	}

	public function testAgentTagsFormAHardBoundary(): void {
		$catalog = new AgentCapabilityCatalog([
			$this->capability('crm_read', 'Reads CRM records.', ['crm', 'readonly'], 0),
			$this->capability('admin_delete', 'Deletes system data.', ['administration'], 100)
		]);

		$selection = (new HybridAgentCapabilitySelector())->select(
			$catalog,
			new AgentCapabilitySelectionRequest(
				iteration: 1,
				contextText: 'Delete a system record.',
				config: new AgentCapabilitySelectionConfig(
					maxTools: 4,
					includeTags: ['crm']
				)
			)
		);

		$this->assertSame(['crm_read'], $selection->getToolNames());
	}

	public function testProfileAwareSelectorTreatsLegacySemanticStrategyAsDeterministicHybrid(): void {
		$catalog = new AgentCapabilityCatalog([
			$this->capability('list_ilias_plugins', 'List all registered ILIAS plugins.', ['plugins', 'list'], 20, 'plugins'),
			$this->capability('list_ilias_cron_jobs', 'List ILIAS cron jobs.', ['cron', 'list'], 20, 'cron-jobs')
		]);
		$selector = new ProfileAwareAgentCapabilitySelector(new HybridAgentCapabilitySelector());

		$selection = $selector->select(
			$catalog,
			new AgentCapabilitySelectionRequest(
				iteration: 1,
				contextText: 'List all plugins.',
				config: new AgentCapabilitySelectionConfig(
					strategy: AgentCapabilitySelectionConfig::STRATEGY_SEMANTIC,
					maxTools: 1,
					selectAllThreshold: 0
				),
				model: $this->chatModel('{"selected_tools":["list_ilias_cron_jobs"]}')
			)
		);

		$this->assertSame(AgentCapabilitySelectionConfig::STRATEGY_HYBRID, $selection->getStrategy());
		$this->assertSame(['list_ilias_plugins'], $selection->getToolNames());
		$this->assertNull($selection->getModelMetadata());
	}

	public function testSemanticSelectorUsesAiRerankingForAmbiguousListTools(): void {
		$catalog = new AgentCapabilityCatalog([
			$this->capability('list_ilias_cron_jobs', 'List configured ILIAS cron jobs.', ['ilias', 'cron', 'list'], 80, 'cron-jobs'),
			$this->capability('list_ilias_plugins', 'List all registered ILIAS plugins without using cron jobs.', ['ilias', 'plugins', 'list'], 60, 'plugins'),
			$this->capability('get_ilias_plugin', 'Read one ILIAS plugin.', ['ilias', 'plugins', 'details'], 50, 'plugins'),
			$this->capability('update_webdav', 'Update WebDAV settings.', ['ilias', 'webdav'], 70, 'webdav')
		]);
		$hybrid = new HybridAgentCapabilitySelector();
		$selector = new SemanticAgentCapabilitySelector($hybrid);

		$selection = $selector->select(
			$catalog,
			new AgentCapabilitySelectionRequest(
				iteration: 1,
				contextText: 'Welche Plugins habe ich? Bitte nur die Plugin-Liste, keine Cron-Jobs.',
				config: new AgentCapabilitySelectionConfig(
					strategy: AgentCapabilitySelectionConfig::STRATEGY_HYBRID,
					maxTools: 3,
					selectAllThreshold: 0,
					semanticCandidateTools: 4
				),
				model: $this->chatModel('{"selected_tools":["list_ilias_plugins"]}')
			)
		);

		$this->assertSame(AgentCapabilitySelectionConfig::STRATEGY_SEMANTIC, $selection->getStrategy());
		$this->assertSame(['list_ilias_plugins'], $selection->getToolNames());
		$this->assertNotNull($selection->getModelMetadata());
		$this->assertContains('semantic-ai', $selection->getReasons()['list_ilias_plugins']);
	}

	public function testSemanticSelectorFallsBackToHybridForInvalidModelOutput(): void {
		$catalog = new AgentCapabilityCatalog([
			$this->capability('list_ilias_plugins', 'List all registered ILIAS plugins.', ['plugins', 'list'], 20, 'plugins'),
			$this->capability('list_ilias_cron_jobs', 'List ILIAS cron jobs.', ['cron', 'list'], 20, 'cron-jobs')
		]);
		$hybrid = new HybridAgentCapabilitySelector();
		$selector = new SemanticAgentCapabilitySelector($hybrid);

		$selection = $selector->select(
			$catalog,
			new AgentCapabilitySelectionRequest(
				iteration: 1,
				contextText: 'List all plugins.',
				config: new AgentCapabilitySelectionConfig(
					strategy: AgentCapabilitySelectionConfig::STRATEGY_HYBRID,
					maxTools: 1,
					selectAllThreshold: 0,
					semanticCandidateTools: 2
				),
				model: $this->chatModel('not-json')
			)
		);

		$this->assertSame(['list_ilias_plugins'], $selection->getToolNames());
		$this->assertContains('semantic-invalid-output', $selection->getReasons()['list_ilias_plugins']);
		$this->assertNotNull($selection->getModelMetadata());
	}

	private function capability(
		string $name,
		string $description,
		array $tags,
		int $priority,
		string $sourceId = 'test'
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
						'properties' => ['query' => ['type' => 'string']]
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
