<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AiResultMetadata;
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Api\IAgentCapabilitySourceMetadata;
use MissionBay\Api\IAgentTool;
use MissionBay\Capability\AgentCapabilityCatalogBuilder;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use MissionBay\Orchestrator\Decision\AgentSelectedNativeModelDecisionStrategy;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use PHPUnit\Framework\TestCase;

final class AgentSelectedNativeModelDecisionStrategyTest extends TestCase {

	public function testMainAgentCanReplaceCapabilitySourcesRepeatedlyWithinOneTurn(): void {
		$reporting = new AgentSelectedNativeSourceToolDouble(
			'ilias-reporting',
			'ILIAS Reporting',
			'Queries structured ILIAS data for reporting and aggregation.',
			['report_query']
		);
		$retrieval = new AgentSelectedNativeSourceToolDouble(
			'ilias_retrieval',
			'ILIAS Retrieval',
			'Searches indexed ILIAS documents and content.',
			['retrieval_search']
		);
		$tools = [$reporting, $retrieval];
		$catalog = $this->catalog($tools);
		$model = new AgentSelectedNativeQueueModel([
			new AiChatResult(
				'',
				[new AiToolCall('source-1', AgentSelectedNativeModelDecisionStrategy::SOURCE_SELECTION_TOOL, [
					'source_ids' => ['ilias-reporting']
				])],
				new AiResultMetadata('model_decision', 'test', 'source-reporting')
			),
			new AiChatResult(
				'',
				[new AiToolCall('source-2', AgentSelectedNativeModelDecisionStrategy::SOURCE_SELECTION_TOOL, [
					'source_ids' => ['ilias_retrieval']
				])],
				new AiResultMetadata('model_decision', 'test', 'source-retrieval')
			),
			new AiChatResult(
				'',
				[new AiToolCall('call-1', 'retrieval_search', ['query' => 'Kursinhalt'])],
				new AiResultMetadata('model_decision', 'test', 'retrieval-call')
			)
		]);
		$context = $this->context($model, $tools, $catalog);
		$strategy = new AgentSelectedNativeModelDecisionStrategy();

		$first = $strategy->decide($context, AgentModelDecisionConfig::nativeCapability());
		$this->apply($context, $first->getPatch());

		$this->assertSame(AgentToolLoopContextKeys::PHASE_MODEL, $first->getPatch()[AgentToolLoopContextKeys::PHASE]);
		$this->assertSame(['report_query'], $first->getPatch()[AgentToolLoopContextKeys::SELECTED_TOOL_NAMES]);
		$this->assertSame(
			[AgentSelectedNativeModelDecisionStrategy::SOURCE_SELECTION_TOOL],
			$this->toolNames($model->getToolSets()[0])
		);
		$this->assertStringContainsString('ilias-reporting', $this->systemText($model->getMessageSets()[0]));
		$this->assertStringContainsString('ilias_retrieval', $this->systemText($model->getMessageSets()[0]));
		$this->assertStringContainsString('Queries structured ILIAS data for reporting and aggregation.', $this->systemText($model->getMessageSets()[0]));

		$context->setVar(AgentToolLoopContextKeys::ITERATION, 2);
		$second = $strategy->decide($context, AgentModelDecisionConfig::nativeCapability());
		$this->apply($context, $second->getPatch());

		$this->assertSame(AgentToolLoopContextKeys::PHASE_MODEL, $second->getPatch()[AgentToolLoopContextKeys::PHASE]);
		$this->assertSame(['retrieval_search'], $second->getPatch()[AgentToolLoopContextKeys::SELECTED_TOOL_NAMES]);
		$this->assertSame(
			['report_query', AgentSelectedNativeModelDecisionStrategy::SOURCE_SELECTION_TOOL],
			$this->toolNames($model->getToolSets()[1])
		);

		$context->setVar(AgentToolLoopContextKeys::ITERATION, 3);
		$third = $strategy->decide($context, AgentModelDecisionConfig::nativeCapability());

		$this->assertSame(AgentToolLoopContextKeys::PHASE_TOOLS, $third->getPatch()[AgentToolLoopContextKeys::PHASE]);
		$this->assertSame('retrieval_search', $third->getPatch()[AgentToolLoopContextKeys::PENDING_TOOL_CALLS][0]->getName());
		$this->assertSame(
			['retrieval_search', AgentSelectedNativeModelDecisionStrategy::SOURCE_SELECTION_TOOL],
			$this->toolNames($model->getToolSets()[2])
		);
		$this->assertNotContains('report_query', $third->getPatch()[AgentToolLoopContextKeys::SELECTED_TOOL_NAMES]);
	}

	public function testInvalidSourceSelectionIsReturnedToMainAgentInsteadOfFailingOrchestration(): void {
		$reporting = new AgentSelectedNativeSourceToolDouble(
			'ilias-reporting',
			'ILIAS Reporting',
			'Queries structured ILIAS data.',
			['report_query']
		);
		$model = new AgentSelectedNativeQueueModel([
			new AiChatResult(
				'',
				[new AiToolCall('source-invalid', AgentSelectedNativeModelDecisionStrategy::SOURCE_SELECTION_TOOL, [
					'source_ids' => ['does-not-exist']
				])],
				new AiResultMetadata('model_decision', 'test', 'invalid-source')
			)
		]);
		$context = $this->context($model, [$reporting], $this->catalog([$reporting]));

		$patch = (new AgentSelectedNativeModelDecisionStrategy())
			->decide($context, AgentModelDecisionConfig::nativeCapability())
			->getPatch();

		$this->assertSame(AgentToolLoopContextKeys::PHASE_MODEL, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertSame('', $context->getVar(AgentToolLoopContextKeys::FAILURE_CODE));
		$this->assertSame([], $patch[AgentToolLoopContextKeys::PENDING_TOOL_CALLS]);
		$this->assertSame([], $patch[AgentToolLoopContextKeys::SELECTED_TOOL_NAMES]);
		$lastMessage = end($patch[AgentToolLoopContextKeys::MESSAGES]);
		$this->assertSame('tool', $lastMessage['role'] ?? null);
		$this->assertStringContainsString('Unknown capability source id', (string)($lastMessage['content'] ?? ''));
	}

	public function testSourceSwitchTakesPrecedenceOverMixedDomainCalls(): void {
		$reporting = new AgentSelectedNativeSourceToolDouble(
			'ilias-reporting',
			'ILIAS Reporting',
			'Queries structured ILIAS data.',
			['report_query']
		);
		$model = new AgentSelectedNativeQueueModel([
			new AiChatResult(
				'',
				[
					new AiToolCall('source-1', AgentSelectedNativeModelDecisionStrategy::SOURCE_SELECTION_TOOL, [
						'source_ids' => ['ilias-reporting']
					]),
					new AiToolCall('domain-1', 'report_query', ['query' => 'top courses'])
				],
				new AiResultMetadata('model_decision', 'test', 'mixed-source-call')
			)
		]);
		$context = $this->context($model, [$reporting], $this->catalog([$reporting]));

		$patch = (new AgentSelectedNativeModelDecisionStrategy())
			->decide($context, AgentModelDecisionConfig::nativeCapability())
			->getPatch();

		$this->assertSame(AgentToolLoopContextKeys::PHASE_MODEL, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertSame([], $patch[AgentToolLoopContextKeys::PENDING_TOOL_CALLS]);
		$messages = $patch[AgentToolLoopContextKeys::MESSAGES];
		$this->assertStringContainsString('Capability source working set replaced', (string)($messages[count($messages) - 2]['content'] ?? ''));
		$this->assertStringContainsString('capability_source_switch_precedes_tools', (string)($messages[count($messages) - 1]['content'] ?? ''));
	}

	/** @param array<int,AgentSelectedNativeSourceToolDouble> $tools */
	private function catalog(array $tools): AgentCapabilityCatalog {
		$definitions = [];
		foreach ($tools as $tool) {
			$definitions = array_merge($definitions, $tool->getToolDefinitions());
		}
		return (new AgentCapabilityCatalogBuilder())->build($tools, $definitions);
	}

	/** @param array<int,AgentSelectedNativeSourceToolDouble> $tools */
	private function context(
		IAiChatModel $model,
		array $tools,
		AgentCapabilityCatalog $catalog
	): AgentContext {
		$definitions = [];
		foreach ($tools as $tool) {
			$definitions = array_merge($definitions, $tool->getToolDefinitions());
		}

		return new AgentContext(vars: [
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_MODEL,
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::FAILURE_CODE => '',
			AgentToolLoopContextKeys::MODEL => $model,
			AgentToolLoopContextKeys::MESSAGES => [
				['role' => 'system', 'content' => 'Use tools when runtime evidence is required.'],
				['role' => 'user', 'content' => 'Find the five largest courses and then their content.']
			],
			AgentToolLoopContextKeys::TOOL_DEFINITIONS => $definitions,
			AgentToolLoopContextKeys::MUTATION_TOOL_NAMES => [],
			AgentToolLoopContextKeys::MODEL_DECISION_CONFIG => AgentModelDecisionConfig::nativeCapability(),
			AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS => [],
			AgentToolLoopContextKeys::MODEL_RESULTS => [],
			AgentToolLoopContextKeys::CONTINUATION_HINT => '',
			AgentToolLoopContextKeys::EVENT_CALLBACK => null,
			AgentToolLoopContextKeys::TOOL_RESULTS => [],
			AgentToolLoopContextKeys::OBSERVATIONS => [],
			AgentToolLoopContextKeys::ITERATION => 1,
			AgentToolLoopContextKeys::CAPABILITY_CATALOG => $catalog,
			AgentToolLoopContextKeys::CAPABILITY_SELECTION_CONFIG => AgentCapabilitySelectionConfig::fromArray([
				'enabled' => false,
				'max_tools' => 64,
				'max_sources' => 8,
				'semantic_max_prompt_characters' => 48000
			]),
			AgentToolLoopContextKeys::CAPABILITY_SELECTIONS => [],
			AgentToolLoopContextKeys::CAPABILITY_SELECTION_APPLIED => false,
			AgentToolLoopContextKeys::SELECTED_TOOL_NAMES => [],
			AgentToolLoopContextKeys::REQUIRED_TOOL_NAMES => [],
			AgentToolLoopContextKeys::TOOLS => $tools
		]);
	}

	/** @param array<string,mixed> $patch */
	private function apply(AgentContext $context, array $patch): void {
		foreach ($patch as $key => $value) {
			$context->setVar($key, $value);
		}
	}

	/** @param array<int,array<string,mixed>> $definitions @return array<int,string> */
	private function toolNames(array $definitions): array {
		$result = [];
		foreach ($definitions as $definition) {
			$name = trim((string)($definition['function']['name'] ?? ''));
			if ($name !== '') {
				$result[] = $name;
			}
		}
		return $result;
	}

	/** @param array<int,array<string,mixed>> $messages */
	private function systemText(array $messages): string {
		foreach ($messages as $message) {
			if (($message['role'] ?? null) === 'system') {
				return (string)($message['content'] ?? '');
			}
		}
		return '';
	}
}

final class AgentSelectedNativeSourceToolDouble implements IAgentTool, IAgentCapabilitySourceMetadata {

	/** @param array<int,string> $functionNames */
	public function __construct(
		private readonly string $sourceId,
		private readonly string $label,
		private readonly string $description,
		private readonly array $functionNames
	) {}

	public static function getName(): string {
		return 'agentselectednativesourcetooldouble';
	}

	public function getCapabilitySourceId(): string {
		return $this->sourceId;
	}

	public function getCapabilitySourceLabel(): string {
		return $this->label;
	}

	public function getCapabilitySourceDescription(): string {
		return $this->description;
	}

	public function getToolDefinitions(): array {
		$result = [];
		foreach ($this->functionNames as $name) {
			$result[] = [
				'type' => 'function',
				'readOnlyHint' => true,
				'mutation' => false,
				'requiresApproval' => false,
				'function' => [
					'name' => $name,
					'description' => 'Executes ' . $name . '.',
					'parameters' => [
						'type' => 'object',
						'properties' => ['query' => ['type' => 'string']],
						'required' => []
					]
				]
			];
		}
		return $result;
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		return ['ok' => true];
	}
}

final class AgentSelectedNativeQueueModel implements IAiChatModel {

	/** @var array<int,AiChatResult> */
	private array $results;
	private array $toolSets = [];
	private array $messageSets = [];
	private array $options = [];

	/** @param array<int,AiChatResult> $results */
	public function __construct(array $results) {
		$this->results = array_values($results);
	}

	public function complete(array $messages, array $tools = []): AiChatResult {
		return $this->nextResult($messages, $tools);
	}

	public function chat(array $messages): string {
		return $this->complete($messages)->getContent();
	}

	public function raw(array $messages, array $tools = []): mixed {
		return $this->complete($messages, $tools);
	}

	public function streamResult(array $messages, array $tools, callable $onData, callable $onMeta = null): AiChatResult {
		$result = $this->nextResult($messages, $tools);
		if (!$result->hasToolCalls() && $result->getContent() !== '') {
			$onData($result->getContent());
		}
		return $result;
	}

	public function stream(array $messages, array $tools, callable $onData, callable $onMeta = null): void {
		$this->streamResult($messages, $tools, $onData, $onMeta);
	}

	public function setOptions(array $options): void {
		$this->options = $options;
	}

	public function getOptions(): array {
		return $this->options;
	}

	public function getToolSets(): array {
		return $this->toolSets;
	}

	public function getMessageSets(): array {
		return $this->messageSets;
	}

	private function nextResult(array $messages, array $tools): AiChatResult {
		$this->messageSets[] = $messages;
		$this->toolSets[] = $tools;
		$result = array_shift($this->results);
		if (!$result instanceof AiChatResult) {
			throw new \RuntimeException('No queued agent-selected native model result available.');
		}
		return $result;
	}
}
