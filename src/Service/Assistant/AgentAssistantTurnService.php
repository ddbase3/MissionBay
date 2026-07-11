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
use MissionBay\Api\IAgentAssistantFallbackBuilder;
use MissionBay\Api\IAgentAssistantMemoryService;
use MissionBay\Api\IAgentAssistantMessageFactory;
use MissionBay\Api\IAgentAssistantToolSetupFactory;
use MissionBay\Api\IAgentAssistantTurnService;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentResume;
use AssistantFoundation\Exception\AgentSuspensionRepositoryException;
use MissionBay\Dto\Assistant\AgentAssistantTurnOptions;
use MissionBay\Dto\Assistant\AgentAssistantTurnResources;
use MissionBay\Dto\Assistant\AgentAssistantTurnResult;
use MissionBay\Dto\Assistant\PreparedAgentResume;
use MissionBay\Orchestrator\AgentStagePipelineResolver;
use MissionBay\Orchestrator\AgentToolOrchestrator;
use MissionBay\Orchestrator\AgentToolOrchestratorResult;
use MissionBay\Orchestrator\Service\AgentActionResumeService;

final class AgentAssistantTurnService implements IAgentAssistantTurnService {

	public function __construct(
		private IAgentAssistantMemoryService $memoryService,
		private IAgentAssistantMessageFactory $messageFactory,
		private IAgentAssistantToolSetupFactory $toolSetupFactory,
		private AgentStagePipelineResolver $stagePipelineResolver,
		private AgentToolOrchestrator $orchestrator,
		private AgentActionResumeService $actionResumeService,
		private IAgentAssistantFallbackBuilder $fallbackBuilder
	) {
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
		$selectionPrompt = $this->resolveSelectionPrompt($prompt, $preparedResume);

		if ($prompt === '' && $resume === null) {
			throw new \RuntimeException('Prompt is required for a new agent turn.');
		}

		$this->applyTurnContext($context, $assistantMessageId, $logger);

		$memories = $options->isMemoryReadEnabled()
			? $this->memoryService->sortMemories($resources->getMemories())
			: [];

		$tools = $resources->getTools();
		$toolDefs = [];

		if ($options->areToolsEnabled()) {
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
				$system = rtrim($system) . "

" . trim($systemAppend);
			}

			$tools = $toolSetup->getTools();
			$toolDefs = $toolSetup->getToolDefs();
			$this->log($logger, 'Number of tools: ' . count($toolDefs) . '.');
		} else {
			$tools = [];
			$this->log($logger, 'Tools disabled for mode: ' . $options->getMode() . '.');
		}

		$this->log($logger, 'Max tool loops: ' . $options->getMaxToolLoops() . '.');

		if ($preparedResume !== null) {
			$messages = $this->readResumeMessages($preparedResume);
			$userMessage = $this->resolveResumeUserMessage($preparedResume);
		} else {
			$messages = $this->memoryService->buildInitialMessages($system, $memories, $nodeId, $logger);
			$userMessage = $this->messageFactory->createUserMessage($prompt);
			$messages[] = $userMessage;

			if ($options->isMemoryWriteEnabled()) {
				$this->memoryService->appendVisibleMessage($memories, $nodeId, $userMessage, $logger);
			}
		}

		if (!$options->areToolsEnabled()) {
			$this->storeSkippedOrchestratorContext($context, $messages, $logger);

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

		$stages = $this->stagePipelineResolver->resolve($options->getStageIds());
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
			$preparedResume
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

	private function applyTurnContext(IAgentContext $context, string $assistantMessageId, ?ILogger $logger): void {
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
