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

namespace Base3\MissionBay\Test\Orchestrator;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Dto\AgentCapabilityCatalog;
use Base3\AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use Base3\AssistantFoundation\Dto\AiChatResult;
use Base3\AssistantFoundation\Dto\AiResultMetadata;
use Base3\AssistantFoundation\Dto\AiToolCall;
use Base3\MissionBay\Api\IAgentCapabilitySourceMetadata;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Capability\AgentCapabilityCatalogBuilder;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use Base3\MissionBay\Orchestrator\Decision\AgentSelectedNativeModelDecisionStrategy;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
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

require_once __DIR__ . '/AgentSelectedNativeSourceToolDouble.php';
require_once __DIR__ . '/AgentSelectedNativeQueueModel.php';
