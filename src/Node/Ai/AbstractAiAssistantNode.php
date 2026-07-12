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

namespace MissionBay\Node\Ai;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentContextContributor;
use MissionBay\Api\IAgentStateContext;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentBudget;
use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use AssistantFoundation\Dto\AgentCapabilitySourceConfig;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentResult;
use AssistantFoundation\Dto\AgentResultState;
use AssistantFoundation\Dto\AgentResume;
use AssistantFoundation\Dto\AgentToolCacheConfig;
use Base3\Logger\Api\ILogger;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Api\IAgentAssistantFallbackBuilder;
use MissionBay\Api\IAgentAssistantFinalResponseService;
use MissionBay\Api\IAgentAssistantMemoryService;
use MissionBay\Api\IAgentAssistantTurnService;
use AssistantFoundation\Api\IAgentMemory;
use MissionBay\Api\IAgentProfileSelector;
use MissionBay\Api\IAgentTool;
use MissionBay\Dto\Assistant\AgentAssistantTurnOptions;
use MissionBay\Dto\Assistant\AgentAssistantTurnResources;
use MissionBay\Dto\Assistant\AgentAssistantTurnResult;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use MissionBay\Node\AbstractAgentNode;

abstract class AbstractAiAssistantNode extends AbstractAgentNode {

	protected ?ILogger $logger = null;

	public function __construct(
		protected IAgentAssistantTurnService $turnService,
		protected IAgentAssistantFinalResponseService $finalResponseService,
		protected IAgentAssistantMemoryService $memoryService,
		protected IAgentAssistantFallbackBuilder $fallbackBuilder,
		?string $id = null
	) {
		parent::__construct($id);
	}

	/**
	 * @return array<int,AgentNodePort>
	 */
	protected function getCommonInputDefinitions(bool $includeMode = false): array {
		$definitions = [
			new AgentNodePort(
				name: 'prompt',
				description: 'User message. Required for a new turn; omitted when resuming a suspension.',
				type: 'string',
				default: null,
				required: false
			),
			new AgentNodePort(
				name: 'resume',
				description: 'Optional AgentResume payload containing the opaque resume_handle and explicit responses.',
				type: 'array',
				default: [],
				required: false
			),
			new AgentNodePort(
				name: 'system',
				description: 'Optional system message.',
				type: 'string',
				default: 'You are a helpful assistant.',
				required: false
			),
			new AgentNodePort(
				name: 'maxtoolloops',
				description: 'Maximum number of tool orchestration loops.',
				type: 'int',
				default: 10,
				required: false
			),
			new AgentNodePort(
				name: 'orchestratorprofile',
				description: 'Resolved orchestrator profile id for diagnostics and persisted flow inspection.',
				type: 'string',
				default: 'standard',
				required: false
			),
			new AgentNodePort(
				name: 'deliberateplanning',
				description: 'Enables concise profile-driven planning without an additional model call or separate planning stage.',
				type: 'bool',
				default: false,
				required: false
			),
			new AgentNodePort(
				name: 'stages',
				description: 'Ordered configured IAgentStage component ids. An empty list uses the MissionBay default pipeline.',
				type: 'array',
				default: [],
				required: false
			),
			new AgentNodePort(
				name: 'budget',
				description: 'Optional per-run agent budget. Supports token, AI-operation, tool-call, elapsed-time, and generic normalized usage-metric limits.',
				type: 'array',
				default: [],
				required: false
			),
			new AgentNodePort(
				name: 'capabilitysources',
				description: 'Configured component ids that may contribute tools, capability providers, modules, resource providers, and prompt providers to this agent run.',
				type: 'array',
				default: [],
				required: false
			),
			new AgentNodePort(
				name: 'capabilityselection',
				description: 'Optional capability selection policy. Supports strategy, maxTools, selectAllThreshold, include/exclude tools, tags and categories, alwaysAvailable, and sticky.',
				type: 'array',
				default: [],
				required: false
			),
			new AgentNodePort(
				name: 'toolcache',
				description: 'Optional explicit tool-result cache configuration with scoped TTL rules. Disabled unless enabled and rules are provided.',
				type: 'array',
				default: [],
				required: false
			)
		];

		if ($includeMode) {
			$definitions[] = new AgentNodePort(
				name: 'mode',
				description: 'Operation mode: "chat" (default) or "suggestions" (read-only memory, no tools).',
				type: 'string',
				default: 'chat',
				required: false
			);
		}

		return $definitions;
	}

	/**
	 * @return array<int,AgentNodeDock>
	 */
	protected function getCommonDockDefinitions(): array {
		return [
			new AgentNodeDock(
				name: 'chatmodel',
				description: 'Chat model implementing IAiChatModel.',
				interface: IAiChatModel::class,
				maxConnections: 1,
				required: true
			),
			new AgentNodeDock(
				name: 'memory',
				description: 'Optional conversation-memory resources.',
				interface: IAgentMemory::class,
				maxConnections: 99,
				required: false
			),
			new AgentNodeDock(
				name: 'contextcontributors',
				description: 'Optional prompt/context contributors that are read for new turns and never receive chat-history writes.',
				interface: IAgentContextContributor::class,
				maxConnections: 99,
				required: false
			),
			new AgentNodeDock(
				name: 'logger',
				description: 'Optional logger.',
				interface: ILogger::class,
				maxConnections: 1,
				required: false
			),
			new AgentNodeDock(
				name: 'tools',
				description: 'Callable tools forming the agent capability pool. Per-model-call exposure is bounded by capabilityselection.',
				interface: IAgentTool::class,
				maxConnections: 512,
				required: false
			),
			new AgentNodeDock(
				name: 'profileselector',
				description: 'Optional profile selector that returns ProfilePlans (supports multi-profile).',
				interface: IAgentProfileSelector::class,
				maxConnections: 1,
				required: false
			)
		];
	}

	protected function buildTurnResources(array $resources, string $missingModelMessage): AgentAssistantTurnResources {
		$this->logger = $this->readLogger($resources);
		$model = $resources['chatmodel'][0] ?? null;

		if (!$model instanceof IAiChatModel) {
			throw new \RuntimeException($missingModelMessage);
		}

		return new AgentAssistantTurnResources(
			model: $model,
			memories: $this->readMemories($resources),
			contextContributors: $this->readContextContributors($resources),
			tools: $this->readTools($resources),
			logger: $this->logger,
			profileSelector: $this->readProfileSelector($resources)
		);
	}

	protected function buildTurnOptions(
		array $inputs,
		string $assistantMessageId,
		bool $toolsEnabled = true,
		bool $memoryReadEnabled = true,
		bool $memoryWriteEnabled = true,
		string $mode = 'chat'
	): AgentAssistantTurnOptions {
		return new AgentAssistantTurnOptions(
			prompt: trim((string)($inputs['prompt'] ?? '')),
			system: trim((string)($inputs['system'] ?? 'You are a helpful assistant.')),
			maxToolLoops: $this->readMaxToolLoops($inputs),
			toolsEnabled: $toolsEnabled,
			memoryReadEnabled: $memoryReadEnabled,
			memoryWriteEnabled: $memoryWriteEnabled,
			mode: $mode,
			nodeId: $this->getId(),
			assistantMessageId: $assistantMessageId,
			stageIds: $this->readStageIds($inputs),
			budget: $this->readBudget($inputs),
			toolCacheConfig: $this->readToolCacheConfig($inputs),
			resume: $this->readResume($inputs),
			capabilitySelectionConfig: $this->readCapabilitySelectionConfig($inputs),
			capabilitySourceConfig: $this->readCapabilitySourceConfig($inputs),
			deliberatePlanningEnabled: $this->readBoolInput($inputs, 'deliberateplanning', false)
		);
	}

	protected function appendAssistantMessageToMemory(AgentAssistantTurnResult $turnResult, array $assistantMessage): void {
		if (!$turnResult->shouldWriteMemory()) {
			return;
		}

		$this->memoryService->appendVisibleMessage(
			$turnResult->getMemories(),
			$turnResult->getNodeId(),
			$assistantMessage,
			$this->logger
		);
	}

	protected function storeModelResults(IAgentContext $context, AgentAssistantTurnResult $turnResult): void {
		try {
			$context->setVar('agent_model_results', $turnResult->getModelResults());
		} catch(\Throwable $e) {
			$this->logError('Model result metadata could not be stored: ' . $e->getMessage());
		}
	}

	/**
	 * Completes the stable typed result after the visible final response has
	 * been generated. The orchestrator result remains available unchanged.
	 *
	 * @param array<string,mixed> $assistantMessage
	 */
	protected function finalizeTypedAgentResult(
		IAgentContext $context,
		AgentAssistantTurnResult $turnResult,
		array $assistantMessage,
		string $finalContent
	): void {
		if (!$context instanceof IAgentStateContext) {
			return;
		}

		$previousResult = $turnResult->getAgentResult() ?? $context->getResult();
		$state = $previousResult?->getState() ?? $context->getState();
		$execution = $state->getExecution();
		if ($execution !== null) {
			$state = $state->withExecution($execution->withModelResults($turnResult->getModelResults()));
		}

		$resultState = $state->getResult() ?? new AgentResultState();
		$status = $turnResult->getExecutionStatus();
		$finalResponseMode = $resultState->getFinalResponseMode();
		if ($finalResponseMode === AgentToolLoopContextKeys::FINAL_RESPONSE_NONE) {
			$finalResponseMode = match ($status) {
				AgentExecutionStatus::COMPLETED => AgentToolLoopContextKeys::FINAL_RESPONSE_COMPLETE,
				AgentExecutionStatus::PARTIAL => AgentToolLoopContextKeys::FINAL_RESPONSE_PARTIAL,
				default => AgentToolLoopContextKeys::FINAL_RESPONSE_NONE
			};
		}
		$state = $state->withResult($resultState->withFinalOutput(
			content: $finalContent,
			finalAssistantMessage: $assistantMessage,
			completed: $status === AgentExecutionStatus::COMPLETED,
			finalResponseMode: $finalResponseMode
		));

		$result = new AgentResult(
			status: $status,
			state: $state,
			output: [
				'content' => $finalContent,
				'message' => $assistantMessage
			],
			metadata: $previousResult?->getMetadata() ?? []
		);

		$context->finish($result);
		$context->setVar('agent_state', $state->toArray());
		$context->setVar('agent_result', $result->toArray());
	}

	protected function createAssistantMessageId(): string {
		return uniqid('msg_', true);
	}

	protected function readInputPrompt(array $inputs): string {
		return trim((string)($inputs['prompt'] ?? ''));
	}

	protected function hasResumeInput(array $inputs): bool {
		$value = $inputs['resume'] ?? null;

		return $value !== null && $value !== '' && $value !== [];
	}

	protected function readInputMode(array $inputs): string {
		$mode = strtolower(trim((string)($inputs['mode'] ?? 'chat')));

		if ($mode === '') {
			return 'chat';
		}

		return $mode;
	}

	protected function readBoolInput(array $inputs, string $key, bool $default): bool {
		if (!array_key_exists($key, $inputs) || $inputs[$key] === null || $inputs[$key] === '') {
			return $default;
		}

		$value = $inputs[$key];
		if (is_bool($value)) {
			return $value;
		}
		if (is_int($value) || is_float($value)) {
			return $value !== 0;
		}

		return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
	}

	protected function readMaxToolLoops(array $inputs): int {
		$value = $inputs['maxtoolloops'] ?? 10;

		if ($value === null || $value === '') {
			return 10;
		}

		if (!is_int($value) && !is_float($value) && !is_string($value)) {
			throw new \RuntimeException('Input maxtoolloops must be numeric.');
		}

		if (!is_numeric($value)) {
			throw new \RuntimeException('Input maxtoolloops must be numeric.');
		}

		$maxToolLoops = (int)$value;

		if ($maxToolLoops < 1) {
			throw new \RuntimeException('Input maxtoolloops must be greater than zero.');
		}

		return $maxToolLoops;
	}

	/**
	 * @return array<int,string>
	 */
	protected function readStageIds(array $inputs): array {
		$value = $inputs['stages'] ?? [];

		if ($value === null || $value === '') {
			return [];
		}

		if (!is_array($value)) {
			throw new \RuntimeException('Input stages must be an array of configured stage ids.');
		}

		return $value;
	}


	protected function readCapabilitySourceConfig(array $inputs): AgentCapabilitySourceConfig {
		$value = $inputs['capabilitysources'] ?? [];

		if ($value === null || $value === '') {
			return new AgentCapabilitySourceConfig();
		}

		if (!is_array($value)) {
			throw new \RuntimeException('Input capabilitysources must be an array.');
		}

		return AgentCapabilitySourceConfig::fromArray($value);
	}

	protected function readCapabilitySelectionConfig(array $inputs): AgentCapabilitySelectionConfig {
		$value = $inputs['capabilityselection'] ?? [];

		if ($value === null || $value === '') {
			return new AgentCapabilitySelectionConfig();
		}

		if (!is_array($value)) {
			throw new \RuntimeException('Input capabilityselection must be an array.');
		}

		return AgentCapabilitySelectionConfig::fromArray($value);
	}

	protected function readResume(array $inputs): ?AgentResume {
		$value = $inputs['resume'] ?? [];

		if ($value === null || $value === '') {
			return null;
		}

		if (!is_array($value)) {
			throw new \RuntimeException('Input resume must be an associative array.');
		}

		if ($value === []) {
			return null;
		}

		return AgentResume::fromArray($value);
	}

	protected function readBudget(array $inputs): AgentBudget {
		$value = $inputs['budget'] ?? [];

		if ($value === null || $value === '') {
			return AgentBudget::unlimited();
		}

		if (!is_array($value)) {
			throw new \RuntimeException('Input budget must be an associative array.');
		}

		return AgentBudget::fromArray($value);
	}

	protected function readToolCacheConfig(array $inputs): AgentToolCacheConfig {
		$value = $inputs['toolcache'] ?? [];

		if ($value === null || $value === '') {
			return AgentToolCacheConfig::disabled();
		}

		if (!is_array($value)) {
			throw new \RuntimeException('Input toolcache must be an associative array.');
		}

		return AgentToolCacheConfig::fromArray($value);
	}

	protected function log(string $msg): void {
		if ($this->logger) {
			$this->logger->log(static::getName(), '[' . $this->id . '] ' . $msg);
		}
	}

	protected function logError(string $msg): void {
		$this->log('[ERROR] ' . $msg);
	}

	private function readLogger(array $resources): ?ILogger {
		$logger = $resources['logger'][0] ?? null;

		return $logger instanceof ILogger ? $logger : null;
	}

	/**
	 * @return array<int,IAgentMemory>
	 */
	private function readMemories(array $resources): array {
		$memories = [];

		foreach (($resources['memory'] ?? []) as $memory) {
			if ($memory instanceof IAgentMemory) {
				$memories[] = $memory;
			}
		}

		return $memories;
	}

	/**
	 * @return array<int,IAgentContextContributor>
	 */
	private function readContextContributors(array $resources): array {
		$contributors = [];
		$seen = [];

		// Older flows connected context contributors to the memory dock. Read both
		// locations during the cleanup window, but invoke each object only once.
		foreach (['contextcontributors', 'memory'] as $dockName) {
			foreach (($resources[$dockName] ?? []) as $contributor) {
				if (!$contributor instanceof IAgentContextContributor) {
					continue;
				}

				$objectId = spl_object_id($contributor);
				if (isset($seen[$objectId])) {
					continue;
				}

				$seen[$objectId] = true;
				$contributors[] = $contributor;
			}
		}

		return $contributors;
	}

	/**
	 * @return array<int,IAgentTool>
	 */
	private function readTools(array $resources): array {
		$tools = [];

		foreach (($resources['tools'] ?? []) as $tool) {
			if ($tool instanceof IAgentTool) {
				$tools[] = $tool;
			}
		}

		return $tools;
	}

	private function readProfileSelector(array $resources): ?IAgentProfileSelector {
		$profileSelector = $resources['profileselector'][0] ?? null;

		return $profileSelector instanceof IAgentProfileSelector ? $profileSelector : null;
	}
}
