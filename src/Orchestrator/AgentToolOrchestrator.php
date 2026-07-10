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

namespace MissionBay\Orchestrator;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentStageResult;
use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use MissionBay\Orchestrator\Stage\AgentToolExecutionStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;

/**
 * AgentToolOrchestrator
 *
 * Executes the non-stream tool phase for an agentic assistant through an
 * ordered stage pipeline.
 *
 * The public run contract and result remain unchanged. Internally, the
 * former monolithic loop is now split into a model decision stage and a
 * tool execution stage. Additional stages can be inserted between them
 * without changing the caller-facing assistant turn contract.
 *
 * This class is intentionally transport-neutral.
 */
class AgentToolOrchestrator {

	private ?ILogger $logger = null;

	/**
	 * @var array<int,IAgentStage>
	 */
	private array $stages = [];

	/**
	 * @param ?array<int,IAgentStage> $stages
	 */
	public function __construct(
		?ILogger $logger = null,
		?IEventManager $eventManager = null,
		?array $stages = null
	) {
		$this->logger = $logger;
		$this->stages = $this->normalizeStages($stages ?? [
			new AgentModelDecisionStage(),
			new AgentToolExecutionStage($eventManager)
		]);
	}

	/**
	 * Runs the tool orchestration loop.
	 *
	 * @param array<int,array<string,mixed>> $messages
	 * @param array<int,array<string,mixed>> $toolDefs
	 * @param array<int,mixed> $tools
	 * @param ?callable $eventCallback function(string $event, array $payload): void
	 */
	public function run(
		IAiChatModel $model,
		array $messages,
		array $toolDefs,
		array $tools,
		IAgentContext $context,
		?callable $eventCallback = null,
		int $maxLoops = 8,
		string $nodeId = ''
	): AgentToolOrchestratorResult {
		$runtimeSnapshot = $this->captureRuntimeContext($context);
		$this->initializeStageContext(
			$context,
			$model,
			$messages,
			$toolDefs,
			$tools,
			$eventCallback,
			$maxLoops,
			$nodeId
		);

		try {
			while (
				$this->getInt($context, AgentToolLoopContextKeys::ITERATION) < $maxLoops &&
				!$this->isCompleted($context) &&
				!$this->hasFailure($context)
			) {
				$iteration = $this->getInt($context, AgentToolLoopContextKeys::ITERATION) + 1;
				$context->setVar(AgentToolLoopContextKeys::ITERATION, $iteration);
				$context->setVar(AgentToolLoopContextKeys::PHASE, AgentToolLoopContextKeys::PHASE_MODEL);

				foreach ($this->stages as $stage) {
					if (!$stage->supports($context)) {
						continue;
					}

					$this->applyStageResult($context, $stage->process($context));
				}
			}

			if (!$this->isCompleted($context) && !$this->hasFailure($context)) {
				$this->logError('Tool phase stopped due to max loop limit: ' . $maxLoops . '.');

				$this->applyStageResult($context, AgentStageResult::patch([
					AgentToolLoopContextKeys::FAILURE_CODE => 'max_tool_loops',
					AgentToolLoopContextKeys::FAILURE_MESSAGE => 'Tool phase did not complete within the allowed tool-call loop limit.',
					AgentToolLoopContextKeys::FAILURE_DETAIL => [
						'max_loops' => $maxLoops
					],
					AgentToolLoopContextKeys::COMPLETED => false,
					AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FAILED
				]));
			}

			return $this->buildResult($context);
		} finally {
			$this->restoreRuntimeContext($context, $runtimeSnapshot);
		}
	}

	/**
	 * @param array<int,mixed> $stages
	 * @return array<int,IAgentStage>
	 */
	private function normalizeStages(array $stages): array {
		$result = [];

		foreach ($stages as $stage) {
			if ($stage instanceof IAgentStage) {
				$result[] = $stage;
			}
		}

		return $result;
	}

	/**
	 * @param array<int,array<string,mixed>> $messages
	 * @param array<int,array<string,mixed>> $toolDefs
	 * @param array<int,mixed> $tools
	 * @param ?callable $eventCallback
	 */
	private function initializeStageContext(
		IAgentContext $context,
		IAiChatModel $model,
		array $messages,
		array $toolDefs,
		array $tools,
		?callable $eventCallback,
		int $maxLoops,
		string $nodeId
	): void {
		$values = [
			AgentToolLoopContextKeys::MODEL => $model,
			AgentToolLoopContextKeys::MESSAGES => $messages,
			AgentToolLoopContextKeys::TOOL_DEFINITIONS => $toolDefs,
			AgentToolLoopContextKeys::TOOLS => $tools,
			AgentToolLoopContextKeys::EVENT_CALLBACK => $eventCallback,
			AgentToolLoopContextKeys::LOGGER => $this->logger,
			AgentToolLoopContextKeys::NODE_ID => $nodeId,
			AgentToolLoopContextKeys::TRACE => $this->buildTrace($context),
			AgentToolLoopContextKeys::MAX_LOOPS => $maxLoops,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_MODEL,
			AgentToolLoopContextKeys::ITERATION => 0,
			AgentToolLoopContextKeys::CALL_INDEX => 0,
			AgentToolLoopContextKeys::PENDING_TOOL_CALLS => [],
			AgentToolLoopContextKeys::EXECUTED_TOOL_CALLS => [],
			AgentToolLoopContextKeys::FINAL_ASSISTANT_MESSAGE => null,
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::FAILURE_CODE => '',
			AgentToolLoopContextKeys::FAILURE_MESSAGE => '',
			AgentToolLoopContextKeys::FAILURE_DETAIL => []
		];

		foreach ($values as $key => $value) {
			$context->setVar($key, $value);
		}
	}

	private function applyStageResult(IAgentContext $context, AgentStageResult $result): void {
		foreach ($result->getPatch() as $key => $value) {
			$context->setVar($key, $value);
		}
	}

	private function buildResult(IAgentContext $context): AgentToolOrchestratorResult {
		$messages = $context->getVar(AgentToolLoopContextKeys::MESSAGES);
		$finalAssistantMessage = $context->getVar(AgentToolLoopContextKeys::FINAL_ASSISTANT_MESSAGE);
		$executedToolCalls = $context->getVar(AgentToolLoopContextKeys::EXECUTED_TOOL_CALLS);
		$failureDetail = $context->getVar(AgentToolLoopContextKeys::FAILURE_DETAIL);

		return new AgentToolOrchestratorResult(
			is_array($messages) ? $messages : [],
			is_array($finalAssistantMessage) ? $finalAssistantMessage : null,
			$this->isCompleted($context),
			$this->getInt($context, AgentToolLoopContextKeys::ITERATION),
			is_array($executedToolCalls) ? $executedToolCalls : [],
			(string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? ''),
			(string)($context->getVar(AgentToolLoopContextKeys::FAILURE_MESSAGE) ?? ''),
			is_array($failureDetail) ? $failureDetail : []
		);
	}

	private function isCompleted(IAgentContext $context): bool {
		return $context->getVar(AgentToolLoopContextKeys::COMPLETED) === true;
	}

	private function hasFailure(IAgentContext $context): bool {
		return (string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? '') !== '';
	}

	private function getInt(IAgentContext $context, string $key): int {
		return (int)($context->getVar($key) ?? 0);
	}

	/**
	 * Captures pre-existing temporary runtime values so the public context is
	 * restored even when a stage throws.
	 *
	 * @return array<string,array{exists:bool,value:mixed}>
	 */
	private function captureRuntimeContext(IAgentContext $context): array {
		$knownKeys = array_fill_keys($context->listVars(), true);
		$snapshot = [];

		foreach (AgentToolLoopContextKeys::getTemporaryRuntimeKeys() as $key) {
			$exists = isset($knownKeys[$key]);
			$snapshot[$key] = [
				'exists' => $exists,
				'value' => $exists ? $context->getVar($key) : null
			];
		}

		return $snapshot;
	}

	/**
	 * @param array<string,array{exists:bool,value:mixed}> $snapshot
	 */
	private function restoreRuntimeContext(IAgentContext $context, array $snapshot): void {
		foreach ($snapshot as $key => $entry) {
			if ($entry['exists']) {
				$context->setVar($key, $entry['value']);
				continue;
			}

			$context->forgetVar($key);
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private function buildTrace(IAgentContext $context): array {
		$configGroup = $this->readContextString($context, ['config_group', 'chatbot_config_group'], 'unknown_group');
		$configName = $this->readContextString($context, ['config_name', 'chatbot_config_name'], 'unknown_config');
		$chatbotKey = $this->readContextString($context, ['chatbot_key', 'chatbot_id', 'chatbot_name'], '');

		if ($chatbotKey === '') {
			$chatbotKey = $this->buildChatbotKey($configGroup, $configName);
		}

		return [
			'turn_id' => $this->readContextString($context, ['turn_id', 'chat_turn_id', 'message_id'], 'unknown_turn'),
			'chatbot_key' => $chatbotKey,
			'config_group' => $configGroup,
			'config_name' => $configName
		];
	}

	private function buildChatbotKey(string $configGroup, string $configName): string {
		if ($configGroup !== 'unknown_group' && $configName !== 'unknown_config') {
			return $configGroup . ':' . $configName;
		}

		if ($configName !== 'unknown_config') {
			return $configName;
		}

		return 'unknown_chatbot';
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

	private function log(string $message): void {
		if ($this->logger === null) {
			return;
		}

		$this->logger->log('agenttoolorchestrator', $message);
	}

	private function logError(string $message): void {
		$this->log('[ERROR] ' . $message);
	}
}
