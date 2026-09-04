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

namespace MissionBay\Orchestrator\Decision;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentCapability;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentCapabilitySelection;
use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Api\IAgentModelDecisionStrategy;
use MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use MissionBay\Orchestrator\Service\AgentCapabilitySourceCatalogService;
use MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;

/**
 * Native model decision where the main agent controls its active capability
 * sources from the complete configured source catalog.
 *
 * The source-control function is internal orchestration control. It can be
 * called repeatedly during one turn and replaces the active working set for
 * the next model decision. Domain calls continue through the existing policy,
 * execution, compaction and observation stages.
 */
final class AgentSelectedNativeModelDecisionStrategy implements IAgentModelDecisionStrategy {

	public const SOURCE_SELECTION_TOOL = 'missionbay_select_capability_sources';

	private NativeAgentModelDecisionStrategy $nativeStrategy;
	private AgentCapabilitySourceCatalogService $sourceCatalogService;
	private AgentToolDefinitionSemantics $toolDefinitionSemantics;

	public function __construct(
		?NativeAgentModelDecisionStrategy $nativeStrategy = null,
		?AgentCapabilitySourceCatalogService $sourceCatalogService = null,
		?AgentToolDefinitionSemantics $toolDefinitionSemantics = null
	) {
		$this->nativeStrategy = $nativeStrategy ?? new NativeAgentModelDecisionStrategy();
		$this->sourceCatalogService = $sourceCatalogService ?? new AgentCapabilitySourceCatalogService();
		$this->toolDefinitionSemantics = $toolDefinitionSemantics ?? new AgentToolDefinitionSemantics();
	}

	public static function getName(): string {
		return AgentModelDecisionConfig::STRATEGY_NATIVE_CAPABILITY;
	}

	public function decide(IAgentContext $context, AgentModelDecisionConfig $config): AgentStageResult {
		$catalog = $context->getVar(AgentToolLoopContextKeys::CAPABILITY_CATALOG);
		$selectionConfig = $context->getVar(AgentToolLoopContextKeys::CAPABILITY_SELECTION_CONFIG);
		$tools = $context->getVar(AgentToolLoopContextKeys::TOOLS);
		$requiredToolNames = $context->getVar(AgentToolLoopContextKeys::REQUIRED_TOOL_NAMES);
		$iteration = (int)($context->getVar(AgentToolLoopContextKeys::ITERATION) ?? 0);

		if (!$catalog instanceof AgentCapabilityCatalog) {
			return $this->failure('capability_catalog_missing', 'Agent-selected native decision did not receive a run-specific capability catalog.');
		}
		if (!$selectionConfig instanceof AgentCapabilitySelectionConfig) {
			$selectionConfig = new AgentCapabilitySelectionConfig();
		}
		if (!is_array($tools)) {
			$tools = [];
		}
		if (!is_array($requiredToolNames)) {
			$requiredToolNames = [];
		}

		try {
			$sources = $this->sourceCatalogService->buildSources(
				$catalog,
				$tools,
				$selectionConfig,
				$requiredToolNames
			);
			if (isset($this->toolNameMap($catalog)[self::SOURCE_SELECTION_TOOL])) {
				throw new \RuntimeException('Configured tool catalog already contains reserved MissionBay tool name: ' . self::SOURCE_SELECTION_TOOL);
			}
			$activeSelection = $this->resolveActiveSelection(
				$context,
				$catalog,
				$sources,
				$selectionConfig,
				$requiredToolNames,
				$iteration
			);
		}
		catch (\Throwable $e) {
			return $this->failure('capability_source_catalog_failed', $e->getMessage());
		}

		$activeDefinitions = $activeSelection->getToolDefinitions();
		$activeToolNames = $activeSelection->getToolNames();
		$activeSourceIds = $this->sourceCatalogService->sourceIdsForToolNames($sources, $activeToolNames);
		$historicalMutationNames = $this->historicalMutationNames($context, $activeDefinitions);
		$catalogInstruction = $this->buildCatalogInstruction(
			$sources,
			$activeSourceIds,
			$selectionConfig
		);

		$originalDefinitions = $context->getVar(AgentToolLoopContextKeys::TOOL_DEFINITIONS);
		$originalContinuationHint = $context->getVar(AgentToolLoopContextKeys::CONTINUATION_HINT);
		$originalMutationToolNames = $context->getVar(AgentToolLoopContextKeys::MUTATION_TOOL_NAMES);

		$context->setVar(
			AgentToolLoopContextKeys::TOOL_DEFINITIONS,
			array_merge($activeDefinitions, [$this->sourceSelectionToolDefinition($selectionConfig)])
		);
		$context->setVar(
			AgentToolLoopContextKeys::CONTINUATION_HINT,
			$this->combineInstructions($catalogInstruction, $originalContinuationHint)
		);
		$context->setVar(AgentToolLoopContextKeys::MUTATION_TOOL_NAMES, $historicalMutationNames);

		try {
			$result = $this->nativeStrategy->decide($context, AgentModelDecisionConfig::native());
		}
		finally {
			$context->setVar(AgentToolLoopContextKeys::TOOL_DEFINITIONS, $originalDefinitions);
			$context->setVar(AgentToolLoopContextKeys::CONTINUATION_HINT, $originalContinuationHint);
			$context->setVar(AgentToolLoopContextKeys::MUTATION_TOOL_NAMES, $originalMutationToolNames);
		}

		$patch = $result->getPatch();
		$pendingCalls = $patch[AgentToolLoopContextKeys::PENDING_TOOL_CALLS] ?? [];
		$pendingCalls = is_array($pendingCalls) ? $pendingCalls : [];
		$sourceCall = $this->sourceSelectionCall($pendingCalls);

		if ($sourceCall instanceof AiToolCall) {
			return $this->handleSourceSelectionCall(
				$context,
				$result,
				$sourceCall,
				$pendingCalls,
				$catalog,
				$sources,
				$selectionConfig,
				$requiredToolNames,
				$activeSelection->getToolNames(),
				$historicalMutationNames,
				$iteration
			);
		}

		if (($patch[AgentToolLoopContextKeys::PHASE] ?? null) === AgentToolLoopContextKeys::PHASE_TOOLS) {
			$patch[AgentToolLoopContextKeys::TOOL_DEFINITIONS] = $activeDefinitions;
			$patch[AgentToolLoopContextKeys::SELECTED_TOOL_NAMES] = $activeToolNames;
			$patch[AgentToolLoopContextKeys::CAPABILITY_SELECTION_APPLIED] = true;
			$patch[AgentToolLoopContextKeys::MUTATION_TOOL_NAMES] = $historicalMutationNames;
		}

		return AgentStageResult::patch($patch, $result->getMetadata());
	}

	/**
	 * @param array<string,array{source_id:string,label:string,description:string,capabilities:array<int,AgentCapability>}> $sources
	 * @param array<int,AiToolCall> $pendingCalls
	 * @param array<int,string> $requiredToolNames
	 * @param array<int,string> $activeToolNames
	 * @param array<int,string> $historicalMutationNames
	 */
	private function handleSourceSelectionCall(
		IAgentContext $context,
		AgentStageResult $delegateResult,
		AiToolCall $sourceCall,
		array $pendingCalls,
		AgentCapabilityCatalog $catalog,
		array $sources,
		AgentCapabilitySelectionConfig $selectionConfig,
		array $requiredToolNames,
		array $activeToolNames,
		array $historicalMutationNames,
		int $iteration
	): AgentStageResult {
		$delegatePatch = $delegateResult->getPatch();
		$messages = $delegatePatch[AgentToolLoopContextKeys::MESSAGES]
			?? $context->getVar(AgentToolLoopContextKeys::MESSAGES);
		$messages = is_array($messages) ? $messages : [];
		$sourceIds = $sourceCall->getArguments()['source_ids'] ?? null;
		$selection = null;
		$error = null;

		if (!is_array($sourceIds) || $sourceIds === []) {
			$error = 'source_ids must contain at least one exact capability source id from the catalog.';
		}
		else {
			try {
				$selection = $this->sourceCatalogService->selectSources(
					$catalog,
					$sources,
					$sourceIds,
					$selectionConfig,
					$requiredToolNames,
					$iteration
				);
			}
			catch (\Throwable $e) {
				$error = $e->getMessage();
			}
		}

		if ($selection !== null && $this->sameToolSet($activeToolNames, $selection->getToolNames())) {
			$error = 'The requested capability source set does not change the active tool working set.';
			$selection = null;
		}

		if ($selection !== null) {
			$selectedDefinitions = $selection->getToolDefinitions();
			$selectedToolNames = $selection->getToolNames();
			$activeSourceIds = $this->sourceCatalogService->sourceIdsForToolNames($sources, $selectedToolNames);
			$historicalMutationNames = $this->mergeNames(
				$historicalMutationNames,
				$this->toolDefinitionSemantics->getMutationToolNames($selectedDefinitions)
			);
			$messages[] = $this->toolMessage($sourceCall, [
				'ok' => true,
				'active_source_ids' => $activeSourceIds,
				'active_tool_names' => $selectedToolNames,
				'message' => 'Capability source working set replaced. Continue the same task using the newly active tools.'
			]);
			$this->appendIgnoredMixedToolMessages($messages, $pendingCalls, $sourceCall);
			$selections = $context->getVar(AgentToolLoopContextKeys::CAPABILITY_SELECTIONS);
			$selections = is_array($selections) ? $selections : [];
			$selections[] = $selection;
			$this->emitSelection($context, $selection->toArray(), $activeSourceIds);

			$patch = $delegatePatch;
			$patch[AgentToolLoopContextKeys::MESSAGES] = $messages;
			$patch[AgentToolLoopContextKeys::TOOL_DEFINITIONS] = $selectedDefinitions;
			$patch[AgentToolLoopContextKeys::SELECTED_TOOL_NAMES] = $selectedToolNames;
			$patch[AgentToolLoopContextKeys::CAPABILITY_SELECTION_APPLIED] = true;
			$patch[AgentToolLoopContextKeys::CAPABILITY_SELECTIONS] = $selections;
			$patch[AgentToolLoopContextKeys::MUTATION_TOOL_NAMES] = $historicalMutationNames;
			$patch[AgentToolLoopContextKeys::PENDING_TOOL_CALLS] = [];
			$patch[AgentToolLoopContextKeys::COMPLETED] = false;
			$patch[AgentToolLoopContextKeys::PHASE] = AgentToolLoopContextKeys::PHASE_MODEL;

			return AgentStageResult::patch($patch, array_merge($delegateResult->getMetadata(), [
				'capability_source_control' => 'selected',
				'active_source_ids' => $activeSourceIds
			]));
		}

		$messages[] = $this->toolMessage($sourceCall, [
			'ok' => false,
			'error_code' => 'capability_source_selection_invalid',
			'error' => $error ?? 'Capability source selection failed.',
			'message' => 'Inspect the capability source catalog and choose a materially different valid source set.'
		]);
		$this->appendIgnoredMixedToolMessages($messages, $pendingCalls, $sourceCall);

		$patch = $delegatePatch;
		$patch[AgentToolLoopContextKeys::MESSAGES] = $messages;
		$patch[AgentToolLoopContextKeys::TOOL_DEFINITIONS] = $this->definitionsForToolNames($catalog, $activeToolNames);
		$patch[AgentToolLoopContextKeys::SELECTED_TOOL_NAMES] = $activeToolNames;
		$patch[AgentToolLoopContextKeys::CAPABILITY_SELECTION_APPLIED] = true;
		$patch[AgentToolLoopContextKeys::MUTATION_TOOL_NAMES] = $historicalMutationNames;
		$patch[AgentToolLoopContextKeys::PENDING_TOOL_CALLS] = [];
		$patch[AgentToolLoopContextKeys::COMPLETED] = false;
		$patch[AgentToolLoopContextKeys::PHASE] = AgentToolLoopContextKeys::PHASE_MODEL;

		return AgentStageResult::patch($patch, array_merge($delegateResult->getMetadata(), [
			'capability_source_control' => 'rejected',
			'error' => $error
		]));
	}

	/**
	 * @param array<string,array{source_id:string,label:string,description:string,capabilities:array<int,AgentCapability>}> $sources
	 * @param array<int,string> $requiredToolNames
	 */
	private function resolveActiveSelection(
		IAgentContext $context,
		AgentCapabilityCatalog $catalog,
		array $sources,
		AgentCapabilitySelectionConfig $selectionConfig,
		array $requiredToolNames,
		int $iteration
	): \AssistantFoundation\Dto\AgentCapabilitySelection {
		$selectionApplied = $context->getVar(AgentToolLoopContextKeys::CAPABILITY_SELECTION_APPLIED) === true;
		$selectedNames = $context->getVar(AgentToolLoopContextKeys::SELECTED_TOOL_NAMES);
		$selectedNames = is_array($selectedNames) ? array_values(array_filter($selectedNames, 'is_string')) : [];

		if ($selectionApplied && $selectedNames !== []) {
			return $this->selectionForToolNames($catalog, $selectedNames, $iteration);
		}

		return $this->sourceCatalogService->selectSources(
			$catalog,
			$sources,
			[],
			$selectionConfig,
			$requiredToolNames,
			$iteration
		);
	}

	/** @param array<int,string> $toolNames */
	private function selectionForToolNames(
		AgentCapabilityCatalog $catalog,
		array $toolNames,
		int $iteration
	): AgentCapabilitySelection {
		$wanted = array_fill_keys($toolNames, true);
		$capabilities = [];
		$scores = [];
		$reasons = [];
		foreach ($catalog->all() as $capability) {
			$name = $capability->getName();
			if (!isset($wanted[$name])) {
				continue;
			}
			$capabilities[] = $capability;
			$scores[$name] = 100.0;
			$reasons[$name] = ['agent-source-selection', 'active-working-set'];
			unset($wanted[$name]);
		}
		if ($wanted !== []) {
			$missing = array_keys($wanted);
			sort($missing);
			throw new \RuntimeException('Previously selected capability is no longer present in the run catalog: ' . implode(', ', $missing));
		}

		return new AgentCapabilitySelection(
			iteration: $iteration,
			strategy: AgentCapabilitySelectionConfig::STRATEGY_ALL,
			catalogSize: count($catalog),
			eligibleSize: count($catalog),
			capabilities: $capabilities,
			scores: $scores,
			reasons: $reasons
		);
	}

	/**
	 * @param array<string,array{source_id:string,label:string,description:string,capabilities:array<int,AgentCapability>}> $sources
	 * @param array<int,string> $activeSourceIds
	 */
	private function buildCatalogInstruction(
		array $sources,
		array $activeSourceIds,
		AgentCapabilitySelectionConfig $config
	): string {
		$catalog = $this->sourceCatalogService->renderCatalog($sources, $config->getSemanticMaxPromptCharacters());
		$active = $activeSourceIds === [] ? 'none' : implode(', ', $activeSourceIds);

		return implode("\n", [
			'<BASE3-CAPABILITY-SOURCE-CATALOG>',
			'The tools currently registered are only your current working set. They are not the complete configured capability universe.',
			'The catalog below is the complete eligible capability-source universe for this run after hard configuration filters.',
			'If the current tools are unsuitable, incomplete, or would require an unreasonable workaround, call `' . self::SOURCE_SELECTION_TOOL . '` and replace the active source working set.',
			'You may call this source-selection function repeatedly during the same user turn. A first source choice is not final. If evidence or a tool error shows that another source is needed, select a better set and continue the same task.',
			'Pass all capability sources needed for the next work step. Source selection replaces the previous active source set instead of accumulating sources forever.',
			'Copy source_ids exactly from the catalog. Do not invent, translate, normalize, alias, or guess source ids.',
			'Do not approximate a task with unsuitable currently loaded tools when a materially better source exists in the catalog.',
			'Before claiming that a capability is unavailable or that the task cannot be completed with tools, inspect this catalog and select the appropriate source when one exists.',
			'When runtime facts, structured data, external information, domain state, or actions are required and a suitable source exists, select and use it instead of filling the answer from model knowledge.',
			'Prefer emitting the source-selection call without unrelated domain tool calls in the same model response. If mixed calls are emitted, MissionBay changes the source set first and requires domain calls to be retried on the next decision.',
			'Currently active capability sources: ' . $active,
			'Configured source limit per active working set: ' . $config->getMaxSources(),
			'Configured function limit per active working set: ' . $config->getMaxTools(),
			'Available capability sources JSON:',
			$catalog,
			'</BASE3-CAPABILITY-SOURCE-CATALOG>'
		]);
	}

	/** @return array<string,mixed> */
	private function sourceSelectionToolDefinition(AgentCapabilitySelectionConfig $config): array {
		return [
			'type' => 'function',
			'readOnlyHint' => true,
			'mutation' => false,
			'requiresApproval' => false,
			'function' => [
				'name' => self::SOURCE_SELECTION_TOOL,
				'description' => 'MissionBay orchestration control. Replace the active capability-source working set for the next model decision. Use it whenever currently loaded tools are unsuitable or insufficient. It may be called repeatedly during the same user turn. Pass the complete source set needed for the next work step, not only newly requested sources. This changes tool exposure only and does not change user or application state.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'source_ids' => [
							'type' => 'array',
							'items' => ['type' => 'string'],
							'minItems' => 1,
							'maxItems' => $config->getMaxSources(),
							'description' => 'Exact source_ids from the capability source catalog. The listed sources replace the current active source set.'
						]
					],
					'required' => ['source_ids'],
					'additionalProperties' => false
				]
			]
		];
	}

	/** @param array<int,AiToolCall> $calls */
	private function sourceSelectionCall(array $calls): ?AiToolCall {
		foreach ($calls as $call) {
			if ($call instanceof AiToolCall && $call->getName() === self::SOURCE_SELECTION_TOOL) {
				return $call;
			}
		}
		return null;
	}

	/** @param array<int,array<string,mixed>> $messages @param array<int,AiToolCall> $calls */
	private function appendIgnoredMixedToolMessages(array &$messages, array $calls, AiToolCall $sourceCall): void {
		foreach ($calls as $call) {
			if (!$call instanceof AiToolCall || $call->getId() === $sourceCall->getId()) {
				continue;
			}
			$messages[] = $this->toolMessage($call, [
				'ok' => false,
				'error_code' => 'capability_source_switch_precedes_tools',
				'error' => 'This domain tool call was not executed because the active capability source set changed in the same model response.',
				'message' => 'Retry the domain operation on the next model decision if it is still required.'
			]);
		}
	}

	/** @param array<string,mixed> $content @return array<string,mixed> */
	private function toolMessage(AiToolCall $call, array $content): array {
		$json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		return [
			'role' => 'tool',
			'tool_call_id' => $call->getId(),
			'content' => is_string($json) ? $json : '{}'
		];
	}

	/** @return array<string,bool> */
	private function toolNameMap(AgentCapabilityCatalog $catalog): array {
		$result = [];
		foreach ($catalog->all() as $capability) {
			$result[$capability->getName()] = true;
		}
		return $result;
	}

	/** @param array<int,string> $toolNames @return array<int,array<string,mixed>> */
	private function definitionsForToolNames(AgentCapabilityCatalog $catalog, array $toolNames): array {
		$wanted = array_fill_keys($toolNames, true);
		$result = [];
		foreach ($catalog->all() as $capability) {
			if (isset($wanted[$capability->getName()])) {
				$result[] = $capability->getDefinition();
			}
		}
		return $result;
	}

	/** @param array<int,array<string,mixed>> $activeDefinitions @return array<int,string> */
	private function historicalMutationNames(IAgentContext $context, array $activeDefinitions): array {
		$existing = $context->getVar(AgentToolLoopContextKeys::MUTATION_TOOL_NAMES);
		$selectionApplied = $context->getVar(AgentToolLoopContextKeys::CAPABILITY_SELECTION_APPLIED) === true;
		$existing = $selectionApplied && is_array($existing) ? array_values(array_filter($existing, 'is_string')) : [];
		return $this->mergeNames($existing, $this->toolDefinitionSemantics->getMutationToolNames($activeDefinitions));
	}

	/** @param array<int,string> $left @param array<int,string> $right @return array<int,string> */
	private function mergeNames(array $left, array $right): array {
		$result = [];
		foreach (array_merge($left, $right) as $name) {
			$name = trim((string)$name);
			if ($name !== '') {
				$result[$name] = true;
			}
		}
		return array_keys($result);
	}

	/** @param array<int,string> $left @param array<int,string> $right */
	private function sameToolSet(array $left, array $right): bool {
		sort($left);
		sort($right);
		return $left === $right;
	}

	private function combineInstructions(string $catalogInstruction, mixed $existing): string {
		$existing = is_scalar($existing) ? trim((string)$existing) : '';
		return $existing === '' ? $catalogInstruction : $catalogInstruction . "\n\n" . $existing;
	}

	/** @param array<string,mixed> $payload @param array<int,string> $sourceIds */
	private function emitSelection(IAgentContext $context, array $payload, array $sourceIds): void {
		$callback = $context->getVar(AgentToolLoopContextKeys::EVENT_CALLBACK);
		if (!is_callable($callback)) {
			return;
		}
		try {
			$callback('capability.selection', array_merge($payload, [
				'selection_owner' => 'main-agent',
				'source_ids' => $sourceIds
			]));
		}
		catch (\Throwable) {
		}
	}

	private function failure(string $code, string $message): AgentStageResult {
		return AgentStageResult::patch([
			AgentToolLoopContextKeys::FAILURE_CODE => $code,
			AgentToolLoopContextKeys::FAILURE_MESSAGE => $message,
			AgentToolLoopContextKeys::FAILURE_DETAIL => [],
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FAILED
		]);
	}
}
