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

namespace MissionBay\Service\Assistant;

use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentAssistantContextContributionService;
use MissionBay\Api\IAgentAssistantFallbackBuilder;
use MissionBay\Api\IAgentAssistantMemoryService;
use MissionBay\Api\IAgentAssistantMessageFactory;
use MissionBay\Api\IAgentAssistantToolSetupFactory;
use MissionBay\Api\IAgentAssistantTurnService;
use MissionBay\Capability\AgentCapabilityDiscoveryService;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentResume;
use AssistantFoundation\Exception\AgentSuspensionRepositoryException;
use MissionBay\Dto\Assistant\AgentAssistantTurnOptions;
use MissionBay\Dto\Assistant\AgentCapabilityDiscoveryResult;
use MissionBay\Dto\Assistant\AgentAssistantTurnResources;
use MissionBay\Dto\Assistant\AgentAssistantTurnResult;
use MissionBay\Dto\Assistant\PreparedAgentResume;
use MissionBay\Orchestrator\AgentStagePipelineResolver;
use MissionBay\Orchestrator\AgentStateSynchronizer;
use MissionBay\Orchestrator\AgentToolOrchestrator;
use MissionBay\Orchestrator\AgentToolOrchestratorResult;
use MissionBay\Orchestrator\Service\AgentActionResumeService;

final class AgentAssistantTurnService implements IAgentAssistantTurnService {

	public function __construct(
		private IAgentAssistantMemoryService $memoryService,
		private IAgentAssistantContextContributionService $contextContributionService,
		private IAgentAssistantMessageFactory $messageFactory,
		private IAgentAssistantToolSetupFactory $toolSetupFactory,
		private AgentCapabilityDiscoveryService $capabilityDiscoveryService,
		private AgentStagePipelineResolver $stagePipelineResolver,
		private AgentToolOrchestrator $orchestrator,
		private AgentActionResumeService $actionResumeService,
		private IAgentAssistantFallbackBuilder $fallbackBuilder,
		private ?AgentStateSynchronizer $stateSynchronizer = null
	) {
		$this->stateSynchronizer ??= new AgentStateSynchronizer();
	}

	public function run(AgentAssistantTurnResources $resources, IAgentContext $context, AgentAssistantTurnOptions $options, ?callable $eventCallback = null): AgentAssistantTurnResult {
		$logger = $resources->getLogger();
		$model = $resources->getModel();
		$nodeId = $options->getNodeId();
		$assistantMessageId = $options->getAssistantMessageId();
		$prompt = $options->getPrompt();
		$system = $options->getSystem();
		$resume = $options->getResume();
		$preparedResume = $this->prepareResume($resume);

		if ($prompt === '' && $resume === null) {
			throw new \RuntimeException('Prompt is required for a new agent turn.');
		}

		$turnId = $this->applyTurnContext($context, $assistantMessageId, $logger);

		$memories = ($options->isMemoryReadEnabled() || $options->isMemoryWriteEnabled())
			? $this->memoryService->sortMemories($resources->getMemories())
			: [];

		$context->setVar('conversation_memory_resource_count', count($memories));
		$this->log($logger, 'Active conversation-memory resource(s): ' . count($memories) . '.');
		if ($options->isMemoryReadEnabled() && $memories === []) {
			$this->logError($logger, 'Conversation memory is enabled for the turn, but no conversation-memory resource is attached.');
		}

		$this->stateSynchronizer->initializeTurn(
			context: $context,
			taskId: $turnId,
			nodeId: $nodeId,
			mode: $options->getMode(),
			conversationMemoryCount: count($memories),
			contextContributorCount: count($resources->getContextContributors()),
			resume: $resume !== null
		);

		$historyMessages = [];
		$userMessage = $preparedResume !== null
			? $this->resolveResumeUserMessage($preparedResume)
			: $this->messageFactory->createUserMessage($prompt);
		$task = [
			'description' => $prompt,
			'selection_prompt' => $this->resolveSelectionPrompt($prompt, $preparedResume),
			'has_history' => false,
			'history_message_count' => 0,
			'completion_criteria' => ['Answer the current user request directly.']
		];

		if ($preparedResume === null) {
			if ($options->isMemoryReadEnabled()) {
				$historyMessages = $this->memoryService->buildInitialMessages('', $memories, $nodeId, $logger);
				array_shift($historyMessages);
			}
			$task = $this->normalizeTask($prompt, $historyMessages);

			// Persist the user turn before capability discovery, policy evaluation, or
			// tool execution can fail. Conversation history must survive every later
			// runtime failure in this turn.
			if ($options->isMemoryWriteEnabled()) {
				$this->memoryService->appendVisibleMessage($memories, $nodeId, $userMessage, $logger);
			}
		}

		$selectionPrompt = (string)$task['selection_prompt'];
		$toolsEnabled = $options->areToolsEnabled();
		$this->stateSynchronizer->updateTask(
			$context,
			(string)$task['description'],
			[
				'prompt' => $prompt,
				'selection_prompt' => $selectionPrompt,
				'has_history' => $task['has_history'],
				'history_message_count' => $task['history_message_count'],
				'completion_criteria' => $task['completion_criteria']
			],
			['normalization' => 'history-context-v2']
		);
		$context->setVar('normalized_task', $task);
		$context->setVar('conversation_history_message_count', (int)$task['history_message_count']);
		$this->log($logger, 'Loaded ' . (int)$task['history_message_count'] . ' visible conversation-history message(s) for node ' . $nodeId . '.');

		$system = rtrim($system) . "\n\nVisible conversation history is the authoritative source for earlier turns in this chat. Use it before tools. Do not use tools, delegated agents, knowledge storage, preferences, or external search to reconstruct the current conversation. Use tools only for information or actions that are not already present in the visible history.";

		if ($options->isDeliberatePlanningEnabled()) {
			$system = rtrim($system) . "\n\nUse a concise execution plan before choosing tools: first use visible history, then identify only missing evidence, use one focused tool batch per evidence gap, avoid repeated equivalent calls, and stop as soon as the answer is supported. Do not reveal private chain-of-thought; only provide the final answer and any necessary uncertainty.";
			$this->stateSynchronizer->updatePlan(
				$context,
				$this->buildDeliberatePlan((bool)$task['has_history'], $toolsEnabled),
				[
					'source' => 'orchestrator-profile',
					'verification' => 'semantic-verification'
				]
			);
		}

		$tools = $resources->getTools();
		$toolDefs = [];
		$capabilityCatalog = null;
		$requiredToolNames = [];
		$capabilitySelectionConfig = $options->getCapabilitySelectionConfig();
		$capabilityDiscovery = null;

		if ($toolsEnabled) {
			$capabilityDiscovery = $this->capabilityDiscoveryService->discover(
				$tools,
				$options->getCapabilitySourceConfig(),
				$context
			);

			foreach ($capabilityDiscovery->getErrors() as $error) {
				$this->logError($logger, $error);
			}
			foreach ($capabilityDiscovery->getWarnings() as $warning) {
				$this->logError($logger, $warning);
			}

			$moduleInstructions = $capabilityDiscovery->getInstructions();
			if ($moduleInstructions !== []) {
				$system = rtrim($system) . "\n\n" . implode("\n\n", $moduleInstructions);
			}

			$tools = $capabilityDiscovery->getTools();
			$toolSetup = $this->toolSetupFactory->create(
				$tools,
				$resources->getProfileSelector(),
				$selectionPrompt,
				$system,
				$context
			);

			if ($toolSetup->wasProfileUnavailable()) {
				$this->logError($logger, 'Requested profiles cannot be fulfilled. Falling back to default behavior.');
				$this->emitEvent($eventCallback, 'profile.unavailable', [
					'message' => 'Requested profiles cannot be fulfilled due to missing tools. Falling back to default behavior.',
					'missing_required_tools' => $toolSetup->getMissingRequiredTools()
				]);
			}

			$systemAppend = $toolSetup->getEffectivePlan()->getSystemAppend();
			if ($systemAppend !== null && trim($systemAppend) !== '') {
				$system = rtrim($system) . "\n\n" . trim($systemAppend);
			}

			$tools = $toolSetup->getTools();
			$toolDefs = $toolSetup->getToolDefs();
			$capabilityCatalog = $toolSetup->getCatalog();
			$requiredToolNames = $toolSetup->getEffectivePlan()->getRequiredTools();
			$capabilitySelectionConfig = $capabilitySelectionConfig->withAlwaysAvailable($requiredToolNames);
			$this->log($logger, 'Capability catalog size: ' . count($capabilityCatalog) . '.');
			$this->log($logger, 'Capability selection max tools: ' . $capabilitySelectionConfig->getMaxTools() . '.');
		} else {
			$tools = [];
			$this->log($logger, 'Tools disabled for mode: ' . $options->getMode() . '.');
		}

		$this->log($logger, 'Max tool loops: ' . $options->getMaxToolLoops() . '.');

		if ($preparedResume !== null) {
			// Resume reuses the exact reviewed message set. Context contributors are
			// resolved only for new turns and must not change an approved action.
			$messages = $this->readResumeMessages($preparedResume);
		} else {
			$messages = array_merge(
				[['role' => 'system', 'content' => $system]],
				$historyMessages
			);
			$contextMessages = $this->contextContributionService->buildMessages(
				array_merge($resources->getContextContributors(), $memories),
				$context,
				$logger
			);

			if ($contextMessages !== []) {
				array_splice($messages, 1, 0, $contextMessages);
			}

			$messages[] = $userMessage;
		}

		if (!$toolsEnabled) {
			$this->storeSkippedOrchestratorContext($context, $messages, $logger);
			$this->stateSynchronizer->finishWithoutOrchestration($context);

			return new AgentAssistantTurnResult(
				messages: $messages,
				userMessage: $userMessage,
				memories: $memories,
				nodeId: $nodeId,
				assistantMessageId: $assistantMessageId,
				memoryWriteEnabled: $options->isMemoryWriteEnabled(),
				orchestrationResult: null,
				completed: true,
				fallbackContent: null
			);
		}

		$stages = $this->stagePipelineResolver->resolve(
			$options->getStageIds(),
			$capabilityDiscovery instanceof AgentCapabilityDiscoveryResult ? $capabilityDiscovery->getStageMounts() : []
		);
		$orchestrationResult = $this->orchestrator->run(
			$model,
			$messages,
			$toolDefs,
			$tools,
			$context,
			$eventCallback,
			$options->getMaxToolLoops(),
			$nodeId,
			$logger,
			$stages,
			$options->getBudget(),
			$options->getToolCacheConfig(),
			$preparedResume,
			$capabilityCatalog instanceof AgentCapabilityCatalog ? $capabilityCatalog : null,
			$capabilityDiscovery,
			$capabilitySelectionConfig,
			$requiredToolNames
		);

		$this->storeOrchestratorContext($context, $orchestrationResult, $logger);

		$fallbackContent = null;
		if (!$orchestrationResult->isCompleted() && !$orchestrationResult->isSuspended()) {
			$this->logError($logger, 'Tool phase did not complete: ' . $orchestrationResult->getFailureCode() . ' ' . $orchestrationResult->getFailureMessage());
			$fallbackContent = $this->fallbackBuilder->build($orchestrationResult);
		}

		return new AgentAssistantTurnResult(
			messages: $orchestrationResult->getMessages(),
			userMessage: $userMessage,
			memories: $memories,
			nodeId: $nodeId,
			assistantMessageId: $assistantMessageId,
			memoryWriteEnabled: $options->isMemoryWriteEnabled(),
			orchestrationResult: $orchestrationResult,
			completed: $orchestrationResult->isCompleted(),
			fallbackContent: $fallbackContent
		);
	}

	private function applyTurnContext(IAgentContext $context, string $assistantMessageId, ?ILogger $logger): string {
		$turnId = $this->readContextString($context, ['turn_id', 'chat_turn_id', 'message_id'], '');

		if ($turnId === '') {
			$turnId = $assistantMessageId;
		}

		try {
			$context->setVar('turn_id', $turnId);
			$context->setVar('chat_turn_id', $turnId);
			$context->setVar('assistant_message_id', $assistantMessageId);
		} catch (\Throwable $e) {
			$this->logError($logger, 'Turn context could not be stored: ' . $e->getMessage());
		}

		return $turnId;
	}

	/**
	 * @param array<int,array<string,mixed>> $messages
	 */
	private function storeSkippedOrchestratorContext(IAgentContext $context, array $messages, ?ILogger $logger): void {
		try {
			$context->setVar('orchestrator_messages', $messages);
			$context->setVar('orchestrator_final_assistant', null);
			$context->setVar('orchestrator_final_output', '');
			$context->setVar('orchestrator_final_response_mode', 'complete');
			$context->setVar('orchestrator_iterations', 0);
			$context->setVar('orchestrator_completed', true);
			$context->setVar('orchestrator_failure_code', '');
			$context->setVar('orchestrator_model_results', []);
			$context->setVar('orchestrator_stage_trace', []);
			$context->setVar('orchestrator_context_compactions', []);
			$context->setVar('orchestrator_result_verifications', []);
			$context->setVar('orchestrator_tool_contract_validations', []);
			$context->setVar('orchestrator_continuation_decisions', []);
			$context->setVar('orchestrator_final_response_instruction', '');
			$context->setVar('orchestrator_budget_assessments', []);
			$context->setVar('orchestrator_actions', []);
			$context->setVar('orchestrator_action_decisions', []);
			$context->setVar('orchestrator_tool_cache_records', []);
			$context->setVar('orchestrator_progress_assessments', []);
			$context->setVar('orchestrator_execution_status', AgentExecutionStatus::COMPLETED);
			$context->setVar('orchestrator_interaction_requests', []);
			$context->setVar('orchestrator_resume_handle', '');
			$context->setVar('orchestrator_capability_selections', []);
		} catch (\Throwable $e) {
			$this->logError($logger, 'Orchestrator context could not be stored: ' . $e->getMessage());
		}
	}

	private function storeOrchestratorContext(IAgentContext $context, AgentToolOrchestratorResult $orchestrationResult, ?ILogger $logger): void {
		try {
			$context->setVar('orchestrator_messages', $orchestrationResult->getMessages());
			$context->setVar('orchestrator_final_assistant', $orchestrationResult->getFinalAssistantMessage());
			$context->setVar('orchestrator_final_output', $orchestrationResult->getFinalOutputContent());
			$context->setVar('orchestrator_final_response_mode', $orchestrationResult->getFinalResponseMode());
			$context->setVar('orchestrator_iterations', $orchestrationResult->getIterations());
			$context->setVar('orchestrator_completed', $orchestrationResult->isCompleted());
			$context->setVar('orchestrator_failure_code', $orchestrationResult->getFailureCode());
			$context->setVar('orchestrator_model_results', $orchestrationResult->getModelResults());
			$context->setVar('orchestrator_stage_trace', array_map(
				static fn($entry): array => $entry->toArray(),
				$orchestrationResult->getStageTrace()
			));
			$context->setVar('orchestrator_context_compactions', array_map(
				static fn($entry): array => $entry->toArray(),
				$orchestrationResult->getContextCompactions()
			));
			$context->setVar('orchestrator_result_verifications', array_map(
				static fn($entry): array => $entry->toArray(),
				$orchestrationResult->getResultVerifications()
			));
			$context->setVar('orchestrator_tool_contract_validations', array_map(
				static fn($entry): array => $entry->toArray(),
				$orchestrationResult->getToolContractValidations()
			));
			$context->setVar('orchestrator_continuation_decisions', array_map(
				static fn($entry): array => $entry->toArray(),
				$orchestrationResult->getContinuationDecisions()
			));
			$context->setVar('orchestrator_final_response_instruction', $orchestrationResult->getFinalResponseInstruction());
			$context->setVar('orchestrator_budget_assessments', array_map(
				static fn($entry): array => $entry->toArray(),
				$orchestrationResult->getBudgetAssessments()
			));
			$context->setVar('orchestrator_actions', array_map(
				static fn($entry): array => $entry->toArray(),
				$orchestrationResult->getActions()
			));
			$context->setVar('orchestrator_action_decisions', array_map(
				static fn($entry): array => $entry->toArray(),
				$orchestrationResult->getActionDecisions()
			));
			$context->setVar('orchestrator_tool_cache_records', array_map(
				static fn($entry): array => $entry->toArray(),
				$orchestrationResult->getToolCacheRecords()
			));
			$context->setVar('orchestrator_progress_assessments', array_map(
				static fn($entry): array => $entry->toArray(),
				$orchestrationResult->getProgressAssessments()
			));
			$context->setVar('orchestrator_execution_status', $orchestrationResult->getExecutionStatus());
			$context->setVar('orchestrator_interaction_requests', array_map(
				static fn($entry): array => $entry->toArray(),
				$orchestrationResult->getInteractionRequests()
			));
			$context->setVar('orchestrator_resume_handle', $orchestrationResult->getResumeHandle());
			$context->setVar('orchestrator_capability_selections', array_map(
				static fn($entry): array => $entry->toArray(),
				$orchestrationResult->getCapabilitySelections()
			));
		} catch (\Throwable $e) {
			$this->logError($logger, 'Orchestrator context could not be stored: ' . $e->getMessage());
		}
	}

	private function prepareResume(?AgentResume $resume): ?PreparedAgentResume {
		if ($resume === null) {
			return null;
		}

		try {
			return $this->actionResumeService->prepare($resume);
		} catch (AgentSuspensionRepositoryException $e) {
			throw new \RuntimeException(
				'Agent resume failed (' . $e->getReason() . '): ' . $e->getMessage(),
				0,
				$e
			);
		}
	}

	/** @return array<int,array<string,mixed>> */
	private function readResumeMessages(PreparedAgentResume $resume): array {
		$messages = $resume->getSuspension()->getState()['messages'] ?? [];

		return is_array($messages) ? $messages : [];
	}

	/** @return array<string,mixed> */
	private function resolveResumeUserMessage(PreparedAgentResume $resume): array {
		$messages = $this->readResumeMessages($resume);
		for ($index = count($messages) - 1; $index >= 0; $index--) {
			$message = $messages[$index] ?? null;
			if (is_array($message) && ($message['role'] ?? null) === 'user') {
				return $message;
			}
		}

		return $this->messageFactory->createUserMessage('');
	}

	private function resolveSelectionPrompt(string $prompt, ?PreparedAgentResume $resume): string {
		if ($resume === null) {
			return $prompt;
		}

		$messages = $resume->getSuspension()->getState()['messages'] ?? [];
		if (!is_array($messages)) {
			return $prompt;
		}

		for ($index = count($messages) - 1; $index >= 0; $index--) {
			$message = $messages[$index] ?? null;
			if (!is_array($message) || ($message['role'] ?? null) !== 'user') {
				continue;
			}
			$content = trim((string)($message['content'] ?? ''));
			if ($content !== '') {
				return $content;
			}
		}

		return $prompt;
	}

	/**
	 * Builds a language-neutral task view. Recent visible conversation turns are
	 * always included in capability selection; no phrase classifier or regular
	 * expression is used to guess whether a request refers to prior messages.
	 *
	 * @param array<int,array<string,mixed>> $historyMessages
	 * @return array<string,mixed>
	 */
	private function normalizeTask(string $prompt, array $historyMessages): array {
		$prompt = trim($prompt);
		$historyContext = $this->buildHistorySelectionContext($historyMessages);
		$selectionPrompt = $prompt;

		if ($historyContext !== '') {
			$selectionPrompt = implode("\n", [
				'Visible conversation history:',
				$historyContext,
				'',
				'Current user request:',
				$prompt
			]);
		}

		return [
			'description' => $prompt,
			'selection_prompt' => $selectionPrompt,
			'has_history' => $historyMessages !== [],
			'history_message_count' => count($historyMessages),
			'desired_output' => 'direct_answer',
			'completion_criteria' => [
				'Use visible conversation history before selecting tools.',
				'Use tools only for information or actions missing from the visible history.',
				'Answer directly when the available evidence is sufficient.'
			]
		];
	}

	/**
	 * @param array<int,array<string,mixed>> $messages
	 */
	private function buildHistorySelectionContext(array $messages): string {
		$rows = [];
		$messages = array_slice($messages, -10);

		foreach ($messages as $message) {
			if (!is_array($message)) {
				continue;
			}

			$role = strtolower(trim((string)($message['role'] ?? '')));
			if (!in_array($role, ['user', 'assistant'], true)) {
				continue;
			}

			$content = $message['content'] ?? '';
			if (!is_scalar($content) || trim((string)$content) === '') {
				continue;
			}

			$rows[] = ucfirst($role) . ': ' . $this->limitText(trim((string)$content), 1000);
		}

		return $this->limitText(implode("\n", $rows), 6000);
	}

	/** @return array<int,array<string,mixed>> */
	private function buildDeliberatePlan(bool $hasHistory, bool $toolsEnabled): array {
		$steps = [];

		if ($hasHistory) {
			$steps[] = ['id' => 'review-history', 'label' => 'Review visible conversation history'];
		}

		if ($toolsEnabled) {
			$steps[] = ['id' => 'assess', 'label' => 'Identify information or actions still missing'];
			$steps[] = ['id' => 'gather', 'label' => 'Use focused tools only for missing evidence'];
			$steps[] = ['id' => 'verify', 'label' => 'Verify that available evidence supports the answer'];
		}

		$steps[] = ['id' => 'answer', 'label' => 'Answer directly and state remaining uncertainty'];

		return $steps;
	}

	private function limitText(string $value, int $maxLength): string {
		if (strlen($value) <= $maxLength) {
			return $value;
		}

		if (function_exists('mb_strcut')) {
			return rtrim(mb_strcut($value, 0, $maxLength, 'UTF-8')) . '…';
		}

		return rtrim(substr($value, 0, $maxLength)) . '…';
	}

	/**
	 * @param array<int,string> $keys
	 */
	private function readContextString(IAgentContext $context, array $keys, string $default): string {
		foreach ($keys as $key) {
			try {
				$value = $context->getVar($key);
			} catch (\Throwable $e) {
				continue;
			}

			if (is_scalar($value)) {
				$value = trim((string)$value);

				if ($value !== '') {
					return $value;
				}
			}
		}

		return $default;
	}

	private function emitEvent(?callable $eventCallback, string $event, array $payload): void {
		if ($eventCallback === null) {
			return;
		}

		try {
			$eventCallback($event, $payload);
		} catch (\Throwable $e) {
			// UI event failures must not abort the assistant turn.
		}
	}

	private function log(?ILogger $logger, string $message): void {
		if ($logger === null) {
			return;
		}

		$logger->log('agentassistantturnservice', $message);
	}

	private function logError(?ILogger $logger, string $message): void {
		$this->log($logger, '[ERROR] ' . $message);
	}
}
