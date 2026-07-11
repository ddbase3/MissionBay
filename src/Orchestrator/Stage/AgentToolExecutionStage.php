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

namespace MissionBay\Orchestrator\Stage;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AgentMutationCommitDecision;
use AssistantFoundation\Dto\AgentToolContractValidation;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantFoundation\Dto\AiToolCall;
use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentTool;
use MissionBay\Cache\AgentToolCacheKeyBuilder;
use MissionBay\Cache\NullAgentToolResultCache;
use MissionBay\Event\MissionBayToolFailedEvent;
use MissionBay\Event\MissionBayToolFinishedEvent;
use MissionBay\Event\MissionBayToolStartedEvent;
use MissionBay\Orchestrator\AgentStageResultAccumulator;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\Service\AgentBudgetGuardService;
use MissionBay\Orchestrator\Service\AgentCapabilitySelectionGuardService;
use MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use MissionBay\Orchestrator\Service\AgentResultVerificationService;
use MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use MissionBay\Orchestrator\Service\AgentToolResultCacheService;

/**
 * AgentToolExecutionStage
 *
 * Executes the provider-neutral tool calls produced by the preceding model
 * decision stage and records structured AgentToolResult observations.
 *
 * This stage deliberately does not add tool output to the model message stack.
 * Following stages may assess, filter, compact, or transform the tool results
 * before an observation stage materializes them as model context.
 */
final class AgentToolExecutionStage implements IAgentStage {

	private AgentToolResultCacheService $toolResultCacheService;
	private AgentBudgetGuardService $budgetGuardService;
	private AgentResultVerificationService $resultVerificationService;
	private AgentMutationCommitGuardService $mutationCommitGuardService;
	private AgentToolContractValidationService $toolContractValidationService;
	private AgentCapabilitySelectionGuardService $capabilitySelectionGuardService;

	public function __construct(
		private readonly IEventManager $eventManager,
		private readonly string $id = 'tool-execution',
		private readonly string $stageName = 'tool-execution',
		?AgentToolResultCacheService $toolResultCacheService = null,
		?AgentBudgetGuardService $budgetGuardService = null,
		?AgentResultVerificationService $resultVerificationService = null,
		?AgentMutationCommitGuardService $mutationCommitGuardService = null,
		?AgentToolContractValidationService $toolContractValidationService = null,
		?AgentCapabilitySelectionGuardService $capabilitySelectionGuardService = null
	) {
		$this->toolResultCacheService = $toolResultCacheService
			?? new AgentToolResultCacheService(
				new NullAgentToolResultCache(),
				$this->eventManager,
				new AgentToolCacheKeyBuilder()
			);
		$this->budgetGuardService = $budgetGuardService ?? new AgentBudgetGuardService();
		$this->resultVerificationService = $resultVerificationService ?? new AgentResultVerificationService();
		$this->mutationCommitGuardService = $mutationCommitGuardService
			?? new AgentMutationCommitGuardService(new AgentActionFingerprint(), $this->eventManager);
		$this->toolContractValidationService = $toolContractValidationService
			?? new AgentToolContractValidationService();
		$this->capabilitySelectionGuardService = $capabilitySelectionGuardService
			?? new AgentCapabilitySelectionGuardService();
	}

	public static function getName(): string {
		return 'agenttoolexecutionstage';
	}

	public function id(): string {
		return $this->id;
	}

	public function name(): string {
		return $this->stageName;
	}

	public function getDescription(): string {
		return 'Executes the approved tool batch through cache, budget, commit guard, contract validation, structural verification, and cache-store services.';
	}

	public function getAiUsage(): string {
		return IAgentStage::AI_USAGE_NONE;
	}

	public function supports(IAgentContext $context): bool {
		$phase = $context->getVar(AgentToolLoopContextKeys::PHASE);
		$toolCalls = $context->getVar(AgentToolLoopContextKeys::PENDING_TOOL_CALLS);
		$toolResults = $context->getVar(AgentToolLoopContextKeys::TOOL_RESULTS);

		return (
				($phase === AgentToolLoopContextKeys::PHASE_TOOLS && is_array($toolCalls) && $toolCalls !== [])
				|| ($phase === AgentToolLoopContextKeys::PHASE_AFTER_TOOLS && is_array($toolResults) && $toolResults !== [])
			)
			&& $context->getVar(AgentToolLoopContextKeys::COMPLETED) !== true
			&& (string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? '') === '';
	}

	public function process(IAgentContext $context): AgentStageResult {
		$results = new AgentStageResultAccumulator($context);

		if ($context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_TOOLS) {
			$results->apply(
				$this->toolResultCacheService->process($context, AgentToolResultCacheService::CHECKPOINT_LOOKUP),
				'cache_lookup'
			);
		}

		if ($this->hasFailure($context)) {
			return $results->result();
		}

		if ($context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_TOOLS) {
			$results->apply(
				$this->budgetGuardService->check($context, AgentBudgetGuardService::CHECKPOINT_TOOLS),
				'budget'
			);

			if ($this->hasFailure($context)) {
				return $results->result();
			}

			$results->apply($this->executeTools($context), 'execution');
		}

		if ($this->hasFailure($context)) {
			return $results->result();
		}

		if ($context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_AFTER_TOOLS) {
			$results->apply($this->resultVerificationService->verify($context), 'verification');
		}

		if ($this->hasFailure($context)) {
			return $results->result();
		}

		if ($context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_AFTER_TOOLS) {
			$results->apply(
				$this->toolResultCacheService->process($context, AgentToolResultCacheService::CHECKPOINT_STORE),
				'cache_store'
			);
		}

		return $results->result();
	}

	private function executeTools(IAgentContext $context): AgentStageResult {
		$toolCalls = $context->getVar(AgentToolLoopContextKeys::PENDING_TOOL_CALLS);
		$tools = $context->getVar(AgentToolLoopContextKeys::TOOLS);
		$toolResults = $context->getVar(AgentToolLoopContextKeys::TOOL_RESULTS);
		$executedToolCalls = $context->getVar(AgentToolLoopContextKeys::EXECUTED_TOOL_CALLS);
		$toolCallIndexes = $context->getVar(AgentToolLoopContextKeys::TOOL_CALL_INDEXES);
		$eventCallback = $context->getVar(AgentToolLoopContextKeys::EVENT_CALLBACK);
		$iteration = (int)($context->getVar(AgentToolLoopContextKeys::ITERATION) ?? 0);
		$callIndex = (int)($context->getVar(AgentToolLoopContextKeys::CALL_INDEX) ?? 0);
		$nodeId = (string)($context->getVar(AgentToolLoopContextKeys::NODE_ID) ?? '');
		$trace = $context->getVar(AgentToolLoopContextKeys::TRACE);
		$logger = $context->getVar(AgentToolLoopContextKeys::LOGGER);

		if (!is_array($toolCalls)) {
			$toolCalls = [];
		}

		foreach ($toolCalls as $call) {
			if (!$call instanceof AiToolCall) {
				return $this->failure(
					'invalid_tool_call',
					'Tool execution stage received a non-normalized tool call.',
					['type' => get_debug_type($call)]
				);
			}
		}

		if (!is_array($tools)) {
			$tools = [];
		}

		if (!is_array($toolResults)) {
			$toolResults = [];
		}

		foreach ($toolResults as $toolResult) {
			if (!$toolResult instanceof AgentToolResult) {
				return $this->failure(
					'invalid_tool_result',
					'Tool execution stage received a non-normalized existing tool result.',
					['type' => get_debug_type($toolResult)]
				);
			}
		}

		if (!is_array($executedToolCalls)) {
			$executedToolCalls = [];
		}

		if (!is_array($toolCallIndexes)) {
			$toolCallIndexes = [];
		}

		if (!is_callable($eventCallback)) {
			$eventCallback = null;
		}

		if (!is_array($trace)) {
			$trace = [];
		}

		foreach ($toolCalls as $call) {
			$assignedCallIndex = (int)($toolCallIndexes[$call->getId()] ?? 0);

			if ($assignedCallIndex > 0) {
				$effectiveCallIndex = $assignedCallIndex;
				$callIndex = max($callIndex, $assignedCallIndex);
			} else {
				$callIndex++;
				$effectiveCallIndex = $callIndex;
			}

			$toolResults[] = $this->handleToolCall(
				$call,
				$tools,
				$context,
				$eventCallback,
				$iteration,
				$effectiveCallIndex,
				$executedToolCalls,
				$nodeId,
				$trace,
				$logger
			);
		}

		return AgentStageResult::patch([
			AgentToolLoopContextKeys::PENDING_TOOL_CALLS => [],
			AgentToolLoopContextKeys::TOOL_RESULTS => $toolResults,
			AgentToolLoopContextKeys::EXECUTED_TOOL_CALLS => $executedToolCalls,
			AgentToolLoopContextKeys::CALL_INDEX => $callIndex,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_AFTER_TOOLS
		]);
	}

	private function hasFailure(IAgentContext $context): bool {
		return trim((string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? '')) !== '';
	}

	/**
	 * @param array<int,mixed> $tools
	 * @param array<int,array<string,mixed>> $executedToolCalls
	 * @param ?callable $eventCallback
	 * @param array<string,mixed> $trace
	 */
	private function handleToolCall(
		AiToolCall $call,
		array $tools,
		IAgentContext $context,
		?callable $eventCallback,
		int $iteration,
		int $callIndex,
		array &$executedToolCalls,
		string $nodeId,
		array $trace,
		mixed $logger
	): AgentToolResult {
		$callId = trim($call->getId());
		if ($callId === '') {
			$callId = uniqid('toolcall_', true);
		}

		$toolName = trim($call->getName());
		$args = $call->getArguments();
		$label = $toolName;
		$effectiveCall = $callId === $call->getId()
			? $call
			: new AiToolCall($callId, $toolName, $args, $call->getMetadata());

		if (!$this->capabilitySelectionGuardService->isAllowed($context, $toolName)) {
			return $this->capabilitySelectionGuardService->createFailure($context, $effectiveCall, $iteration);
		}

		$toolObj = $this->findTool($tools, $toolName, $logger);

		if ($toolObj instanceof IAgentTool) {
			try {
				foreach ($toolObj->getToolDefinitions() as $def) {
					if (($def['function']['name'] ?? '') === $toolName) {
						$label = $def['label'] ?? $toolName;
						break;
					}
				}
			} catch (\Throwable $e) {
				$this->logError($logger, 'Reading tool definitions failed (' . $toolName . '): ' . $e->getMessage());
			}
		}

		$metadata = [
			'label' => $label,
			'iteration' => $iteration,
			'call_index' => $callIndex,
			'tool_call' => $call->getMetadata()
		];


		if (!$toolObj instanceof IAgentTool) {
			$warn = 'Tool not found: ' . $toolName;
			$this->logError($logger, $warn);

			$executedToolCalls[] = [
				'tool' => $toolName,
				'arguments' => $args,
				'error' => $warn
			];

			$this->emitEvent($eventCallback, 'tool.error', $this->buildUiPayload([
				'call_id' => $callId,
				'tool' => $toolName,
				'label' => $label,
				'args' => $args,
				'error' => $warn,
				'iteration' => $iteration,
				'call_index' => $callIndex
			], $trace), $logger);

			$this->fireToolFailedEvent(
				$nodeId,
				$callId,
				$toolName,
				$label,
				$args,
				$warn,
				\RuntimeException::class,
				0,
				$iteration,
				$callIndex,
				$trace,
				$logger
			);

			$errorOutput = [
				'ok' => false,
				'error_code' => 'tool_not_found',
				'error' => $warn
			];

			return AgentToolResult::failure(
				$callId,
				$toolName,
				$args,
				'tool_not_found',
				$warn,
				$metadata + ['error_type' => \RuntimeException::class, 'error_code_value' => 0],
				$errorOutput
			);
		}

		$commitDecision = $this->mutationCommitGuardService->validate($call, $context);
		if (!$commitDecision->isAllowed()) {
			$message = trim($commitDecision->getReason());
			if ($message === '') {
				$message = 'Mutation commit was blocked by the final execution guard.';
			}
			$errorCode = $commitDecision->getCode();
			$this->logError($logger, 'Tool blocked before commit (' . $toolName . '): ' . $message);

			$this->emitEvent($eventCallback, 'tool.error', $this->buildUiPayload([
				'call_id' => $callId,
				'tool' => $toolName,
				'label' => $label,
				'args' => $args,
				'error' => $message,
				'error_code' => $errorCode,
				'blocked' => true,
				'iteration' => $iteration,
				'call_index' => $callIndex
			], $trace), $logger);

			$this->fireToolFailedEvent(
				$nodeId,
				$callId,
				$toolName,
				$label,
				$args,
				$message,
				AgentMutationCommitDecision::class,
				$errorCode,
				$iteration,
				$callIndex,
				$trace,
				$logger
			);

			return AgentToolResult::failure(
				$callId,
				$toolName,
				$args,
				$errorCode,
				$message,
				$metadata + [
					'commit_guard' => $commitDecision->toArray(),
					'blocked_before_execution' => true
				],
				[
					'ok' => false,
					'blocked' => true,
					'error_code' => $errorCode,
					'error' => $message
				]
			);
		}

		$this->emitEvent($eventCallback, 'tool.started', $this->buildUiPayload([
			'call_id' => $callId,
			'tool' => $toolName,
			'label' => $label,
			'args' => $args,
			'iteration' => $iteration,
			'call_index' => $callIndex
		], $trace), $logger);

		$this->fireToolStartedEvent(
			$nodeId,
			$callId,
			$toolName,
			$label,
			$args,
			$iteration,
			$callIndex,
			$trace,
			$logger
		);

		$this->log($logger, 'Tool started: ' . $toolName . ' [' . $callId . ']');

		try {
			$result = $toolObj->callTool($toolName, $args, $context);
			$contractValidation = $this->toolContractValidationService->validateOutput($call, $result, [$toolObj]);
			$this->appendContractValidation($context, $contractValidation);

			if (!$contractValidation->passes()) {
				$message = trim($contractValidation->getSummary());
				if ($message === '') {
					$message = 'Tool output failed contract validation.';
				}

				$executedToolCalls[] = [
					'tool' => $toolName,
					'arguments' => $args,
					'error' => $message,
					'error_code' => $contractValidation->getReasonCode(),
					'result_type' => get_debug_type($result),
					'contract_validation' => $contractValidation->toArray()
				];

				$this->emitEvent($eventCallback, 'tool.error', $this->buildUiPayload([
					'call_id' => $callId,
					'tool' => $toolName,
					'label' => $label,
					'args' => $args,
					'error' => $message,
					'error_code' => $contractValidation->getReasonCode(),
					'contract_validation' => $contractValidation->toArray(),
					'iteration' => $iteration,
					'call_index' => $callIndex
				], $trace), $logger);

				$this->fireToolFailedEvent(
					$nodeId,
					$callId,
					$toolName,
					$label,
					$args,
					$message,
					AgentToolContractValidation::class,
					$contractValidation->getReasonCode(),
					$iteration,
					$callIndex,
					$trace,
					$logger
				);

				$this->mutationCommitGuardService->recordCommitResult(
					$call,
					$context,
					true,
					'Tool execution completed, but its output failed the declared contract.',
					[
						'result_type' => get_debug_type($result),
						'output_contract_valid' => false,
						'contract_validation' => $contractValidation->toArray()
					]
				);

				return AgentToolResult::failure(
					$callId,
					$toolName,
					$args,
					$contractValidation->getReasonCode(),
					$message,
					$metadata + [
						'contract_validation' => $contractValidation->toArray(),
						'tool_executed' => true,
						'result_type' => get_debug_type($result)
					],
					[
						'ok' => false,
						'error_code' => $contractValidation->getReasonCode(),
						'error' => $message,
						'issues' => $contractValidation->getIssues()
					]
				);
			}

			$executedToolCalls[] = [
				'tool' => $toolName,
				'arguments' => $args,
				'result' => $result
			];

			$this->emitEvent($eventCallback, 'tool.finished', $this->buildUiPayload([
				'call_id' => $callId,
				'tool' => $toolName,
				'label' => $label,
				'args' => $args,
				'result' => $result,
				'iteration' => $iteration,
				'call_index' => $callIndex
			], $trace), $logger);

			$this->fireToolFinishedEvent(
				$nodeId,
				$callId,
				$toolName,
				$label,
				$args,
				$result,
				$iteration,
				$callIndex,
				$trace,
				$logger
			);

			$this->log($logger, 'Tool finished: ' . $toolName . ' [' . $callId . ']');
			$this->mutationCommitGuardService->recordCommitResult(
				$call,
				$context,
				true,
				'Mutation committed successfully.',
				['result_type' => get_debug_type($result)]
			);

			return AgentToolResult::success(
				$callId,
				$toolName,
				$args,
				$result,
				$metadata
			);
		} catch (\Throwable $e) {
			$this->logError($logger, 'Tool failed (' . $toolName . '): ' . $e->getMessage());
			$this->mutationCommitGuardService->recordCommitResult(
				$call,
				$context,
				false,
				$e->getMessage(),
				['type' => get_class($e), 'code' => $e->getCode()]
			);

			$errorOutput = [
				'ok' => false,
				'error_code' => 'tool_exception',
				'error' => $e->getMessage(),
				'type' => get_class($e),
				'code' => $e->getCode()
			];

			$executedToolCalls[] = [
				'tool' => $toolName,
				'arguments' => $args,
				'error' => $e->getMessage(),
				'type' => get_class($e),
				'code' => $e->getCode()
			];

			$this->emitEvent($eventCallback, 'tool.error', $this->buildUiPayload([
				'call_id' => $callId,
				'tool' => $toolName,
				'label' => $label,
				'args' => $args,
				'error' => $e->getMessage(),
				'type' => get_class($e),
				'code' => $e->getCode(),
				'iteration' => $iteration,
				'call_index' => $callIndex
			], $trace), $logger);

			$this->fireToolFailedEvent(
				$nodeId,
				$callId,
				$toolName,
				$label,
				$args,
				$e->getMessage(),
				get_class($e),
				$e->getCode(),
				$iteration,
				$callIndex,
				$trace,
				$logger
			);

			return AgentToolResult::failure(
				$callId,
				$toolName,
				$args,
				'tool_exception',
				$e->getMessage(),
				$metadata + ['error_type' => get_class($e), 'error_code_value' => $e->getCode()],
				$errorOutput
			);
		}
	}

	private function appendContractValidation(
		IAgentContext $context,
		AgentToolContractValidation $validation
	): void {
		$validations = $context->getVar(AgentToolLoopContextKeys::TOOL_CONTRACT_VALIDATIONS);
		if (!is_array($validations)) {
			$validations = [];
		}

		$validations[] = $validation;
		$context->setVar(AgentToolLoopContextKeys::TOOL_CONTRACT_VALIDATIONS, $validations);
	}

	/**
	 * @param array<int,mixed> $tools
	 */
	private function findTool(array $tools, string $name, mixed $logger): ?IAgentTool {
		foreach ($tools as $tool) {
			if (!$tool instanceof IAgentTool) {
				continue;
			}

			try {
				foreach ($tool->getToolDefinitions() as $def) {
					if (($def['function']['name'] ?? '') === $name) {
						return $tool;
					}
				}
			} catch (\Throwable $e) {
				$this->logError($logger, 'findTool failed while reading tool definitions: ' . $e->getMessage());
			}
		}

		return null;
	}

	/**
	 * @param array<string,mixed> $detail
	 */
	private function failure(string $code, string $message, array $detail): AgentStageResult {
		return AgentStageResult::patch([
			AgentToolLoopContextKeys::FAILURE_CODE => $code,
			AgentToolLoopContextKeys::FAILURE_MESSAGE => $message,
			AgentToolLoopContextKeys::FAILURE_DETAIL => $detail,
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FAILED
		]);
	}

	/**
	 * @param array<string,mixed> $payload
	 * @param array<string,mixed> $trace
	 * @return array<string,mixed>
	 */
	private function buildUiPayload(array $payload, array $trace): array {
		$payload['turn_id'] = (string)($trace['turn_id'] ?? 'unknown_turn');
		$payload['chatbot_key'] = (string)($trace['chatbot_key'] ?? 'unknown_chatbot');

		return $payload;
	}

	private function emitEvent(?callable $eventCallback, string $event, array $payload, mixed $logger): void {
		if ($eventCallback === null) {
			return;
		}

		try {
			$eventCallback($event, $payload);
		} catch (\Throwable $e) {
			$this->logError($logger, 'Tool UI event callback failed (' . $event . '): ' . $e->getMessage());
		}
	}

	/**
	 * @param array<string,mixed> $arguments
	 * @param array<string,mixed> $trace
	 */
	private function fireToolStartedEvent(
		string $nodeId,
		string $callId,
		string $toolName,
		string $label,
		array $arguments,
		int $iteration,
		int $callIndex,
		array $trace,
		mixed $logger
	): void {
		try {
			$this->eventManager->fire(
				new MissionBayToolStartedEvent(
					$nodeId,
					$callId,
					$toolName,
					$label,
					$arguments,
					$iteration,
					'',
					$callIndex,
					$trace
				)
			);
		} catch (\Throwable $e) {
			$this->logError($logger, 'Tool started event failed (' . $toolName . '): ' . $e->getMessage());
		}
	}

	/**
	 * @param array<string,mixed> $arguments
	 * @param array<string,mixed> $trace
	 */
	private function fireToolFinishedEvent(
		string $nodeId,
		string $callId,
		string $toolName,
		string $label,
		array $arguments,
		mixed $result,
		int $iteration,
		int $callIndex,
		array $trace,
		mixed $logger
	): void {
		try {
			$this->eventManager->fire(
				new MissionBayToolFinishedEvent(
					$nodeId,
					$callId,
					$toolName,
					$label,
					$arguments,
					$result,
					$iteration,
					'',
					$callIndex,
					$trace
				)
			);
		} catch (\Throwable $e) {
			$this->logError($logger, 'Tool finished event failed (' . $toolName . '): ' . $e->getMessage());
		}
	}

	/**
	 * @param array<string,mixed> $arguments
	 * @param array<string,mixed> $trace
	 */
	private function fireToolFailedEvent(
		string $nodeId,
		string $callId,
		string $toolName,
		string $label,
		array $arguments,
		string $errorMessage,
		string $errorType,
		int|string $errorCode,
		int $iteration,
		int $callIndex,
		array $trace,
		mixed $logger
	): void {
		try {
			$this->eventManager->fire(
				new MissionBayToolFailedEvent(
					$nodeId,
					$callId,
					$toolName,
					$label,
					$arguments,
					$errorMessage,
					$errorType,
					$errorCode,
					$iteration,
					'',
					$callIndex,
					$trace
				)
			);
		} catch (\Throwable $e) {
			$this->logError($logger, 'Tool failed event failed (' . $toolName . '): ' . $e->getMessage());
		}
	}

	private function log(mixed $logger, string $message): void {
		if (!$logger instanceof ILogger) {
			return;
		}

		$logger->log('agenttoolorchestrator', $message);
	}

	private function logError(mixed $logger, string $message): void {
		$this->log($logger, '[ERROR] ' . $message);
	}
}
