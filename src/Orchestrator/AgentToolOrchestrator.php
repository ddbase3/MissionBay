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
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentActionDecision;
use AssistantFoundation\Dto\AgentBudget;
use AssistantFoundation\Dto\AgentCapability;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use AssistantFoundation\Dto\AgentCapabilitySourceConfig;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentSuspension;
use MissionBay\Dto\Assistant\AgentCapabilityDiscoveryResult;
use MissionBay\Dto\Assistant\PreparedAgentResume;
use MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AgentToolCacheConfig;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantFoundation\Dto\AiToolCall;
use AssistantFoundation\Dto\AgentStageTraceEntry;
use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use MissionBay\Orchestrator\Service\AgentActionResumeService;
use MissionBay\Orchestrator\Service\AgentBudgetGuardService;
use MissionBay\Orchestrator\Service\AgentLoopProgressService;
use MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use MissionBay\Orchestrator\Suspension\UnavailableAgentSuspensionRepository;

/**
 * AgentToolOrchestrator
 *
 * Executes an ordered agent stage pipeline supplied by the composition layer.
 *
 * The orchestrator owns no default stage list. The normal assistant path resolves
 * the active pipeline through AgentStagePipelineResolver and supplies it per
 * run. A constructor pipeline remains available for direct tests and embedded
 * runtimes.
 *
 * This class is intentionally transport-neutral.
 */
class AgentToolOrchestrator {

	private ?ILogger $logger = null;

	/**
	 * @var array<int,IAgentStage>
	 */
	private array $stages = [];

	private AgentActionResumeService $actionResumeService;
	private AgentBudgetGuardService $budgetGuardService;
	private AgentLoopProgressService $loopProgressService;
	private AgentStateSynchronizer $stateSynchronizer;
	private AgentToolDefinitionSemantics $toolDefinitionSemantics;

	/**
	 * @param ?array<int,IAgentStage> $stages
	 */
	public function __construct(
		?ILogger $logger = null,
		?IEventManager $eventManager = null,
		?array $stages = null,
		?AgentActionResumeService $actionResumeService = null,
		?AgentBudgetGuardService $budgetGuardService = null,
		?AgentLoopProgressService $loopProgressService = null,
		?AgentStateSynchronizer $stateSynchronizer = null,
		?AgentToolDefinitionSemantics $toolDefinitionSemantics = null
	) {
		$this->logger = $logger;
		$this->stages = $stages === null
			? []
			: $this->normalizeStages($stages);
		$this->actionResumeService = $actionResumeService
			?? new AgentActionResumeService(
				new AgentActionFingerprint(),
				new UnavailableAgentSuspensionRepository()
			);
		$this->budgetGuardService = $budgetGuardService ?? new AgentBudgetGuardService();
		$this->loopProgressService = $loopProgressService ?? new AgentLoopProgressService();
		$this->stateSynchronizer = $stateSynchronizer ?? new AgentStateSynchronizer();
		$this->toolDefinitionSemantics = $toolDefinitionSemantics ?? new AgentToolDefinitionSemantics();
	}

	/**
	 * Runs the tool orchestration loop.
	 *
	 * @param array<int,array<string,mixed>> $messages
	 * @param array<int,array<string,mixed>> $toolDefs
	 * @param array<int,mixed> $tools
	 * @param ?callable $eventCallback function(string $event, array $payload): void
	 * @param ?array<int,IAgentStage> $stages Ordered per-run stage pipeline
	 */
	public function run(
		IAiChatModel $model,
		array $messages,
		array $toolDefs,
		array $tools,
		IAgentContext $context,
		?callable $eventCallback = null,
		int $maxLoops = 10,
		string $nodeId = '',
		?ILogger $logger = null,
		?array $stages = null,
		?AgentBudget $budget = null,
		?AgentToolCacheConfig $toolCacheConfig = null,
		?PreparedAgentResume $resume = null,
		?AgentCapabilityCatalog $capabilityCatalog = null,
		?AgentCapabilityDiscoveryResult $capabilityDiscovery = null,
		?AgentCapabilitySelectionConfig $capabilitySelectionConfig = null,
		array $requiredToolNames = [],
		?AgentModelDecisionConfig $modelDecisionConfig = null
	): AgentToolOrchestratorResult {
		$effectiveLogger = $logger ?? $this->logger;
		$effectiveStages = $stages === null
			? $this->stages
			: $this->normalizeStages($stages);

		if ($effectiveStages === []) {
			throw new \RuntimeException(
				'Agent stage pipeline must be supplied by the composition layer.'
			);
		}
		$runtimeSnapshot = $this->captureRuntimeContext($context);
		$this->initializeStageContext(
			$context,
			$model,
			$messages,
			$toolDefs,
			$tools,
			$eventCallback,
			$maxLoops,
			$nodeId,
			$effectiveLogger,
			$budget ?? AgentBudget::unlimited(),
			$toolCacheConfig ?? AgentToolCacheConfig::disabled(),
			$resume,
			$capabilityCatalog ?? $this->createFallbackCatalog($toolDefs),
			$capabilityDiscovery ?? new AgentCapabilityDiscoveryResult(
				new AgentCapabilitySourceConfig(),
				tools: array_values(array_filter($tools, static fn(mixed $tool): bool => is_object($tool)))
			),
			$capabilitySelectionConfig ?? new AgentCapabilitySelectionConfig(),
			$requiredToolNames,
			$modelDecisionConfig ?? AgentModelDecisionConfig::simple()
		);
		$this->stateSynchronizer->synchronize($context);

		$resumePending = $resume !== null;

		try {
			if ($resumePending) {
				$context->setVar(AgentToolLoopContextKeys::PHASE, AgentToolLoopContextKeys::PHASE_RESUME);
				$this->applyStageResult($context, $this->actionResumeService->resume($context));
			}

			while (
				($resumePending || $this->getInt($context, AgentToolLoopContextKeys::ITERATION) < $maxLoops) &&
				!$this->isCompleted($context) &&
				!$this->isSuspended($context) &&
				!$this->hasFailure($context)
			) {
				if ($resumePending) {
					$resumePending = false;
				} else {
					$iteration = $this->getInt($context, AgentToolLoopContextKeys::ITERATION) + 1;
					$context->setVar(AgentToolLoopContextKeys::ITERATION, $iteration);
					$context->setVar(AgentToolLoopContextKeys::PHASE, AgentToolLoopContextKeys::PHASE_MODEL);
				}

				if ($context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_MODEL) {
					$this->applyStageResult(
						$context,
						$this->budgetGuardService->check($context, AgentBudgetGuardService::CHECKPOINT_MODEL)
					);
				}

				foreach ($effectiveStages as $stage) {
					if ($this->hasFailure($context) || $this->isSuspended($context)) {
						break;
					}

					$phaseBeforeStage = $context->getVar(AgentToolLoopContextKeys::PHASE);
					$this->executeStage($context, $stage, $eventCallback);

					if (
						$phaseBeforeStage !== AgentToolLoopContextKeys::PHASE_OBSERVED
						&& $context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_OBSERVED
						&& !$this->hasFailure($context)
					) {
						$this->applyStageResult($context, $this->loopProgressService->assess($context));
					}
				}

				if (
					$context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_FINAL
					&& !$this->hasFailure($context)
				) {
					$this->applyStageResult(
						$context,
						$this->budgetGuardService->check($context, AgentBudgetGuardService::CHECKPOINT_FINAL)
					);
				}
			}

			if (!$this->isCompleted($context) && !$this->isSuspended($context) && !$this->hasFailure($context)) {
				$this->logError($effectiveLogger, 'Tool phase stopped due to max loop limit: ' . $maxLoops . '.');

				$this->applyStageResult($context, AgentStageResult::patch([
					AgentToolLoopContextKeys::FAILURE_CODE => 'max_tool_loops',
					AgentToolLoopContextKeys::FAILURE_MESSAGE => 'Tool phase did not complete within the allowed tool-call loop limit.',
					AgentToolLoopContextKeys::FAILURE_DETAIL => [
						'max_loops' => $maxLoops,
						'executed_tool_calls' => count((array)$context->getVar(AgentToolLoopContextKeys::EXECUTED_TOOL_CALLS)),
						'partial_response_allowed' => true
					],
					AgentToolLoopContextKeys::FINAL_RESPONSE_MODE => AgentToolLoopContextKeys::FINAL_RESPONSE_PARTIAL,
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
			if (!$stage instanceof IAgentStage) {
				throw new \RuntimeException('Agent stage pipelines may contain only IAgentStage instances.');
			}

			$result[] = $stage;
		}

		if ($result === []) {
			throw new \RuntimeException('Agent stage pipeline must not be empty.');
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
		string $nodeId,
		?ILogger $logger,
		AgentBudget $budget,
		AgentToolCacheConfig $toolCacheConfig,
		?PreparedAgentResume $resume,
		AgentCapabilityCatalog $capabilityCatalog,
		AgentCapabilityDiscoveryResult $capabilityDiscovery,
		AgentCapabilitySelectionConfig $capabilitySelectionConfig,
		array $requiredToolNames,
		AgentModelDecisionConfig $modelDecisionConfig
	): void {
		$values = [
			AgentToolLoopContextKeys::MODEL => $model,
			AgentToolLoopContextKeys::MESSAGES => $messages,
			AgentToolLoopContextKeys::TOOL_DEFINITIONS => $toolDefs,
			AgentToolLoopContextKeys::MUTATION_TOOL_NAMES => $this->toolDefinitionSemantics->getMutationToolNames($toolDefs),
			AgentToolLoopContextKeys::MODEL_DECISION_CONFIG => $modelDecisionConfig,
			AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS => [],
			AgentToolLoopContextKeys::CAPABILITY_DISCOVERY => $capabilityDiscovery,
			AgentToolLoopContextKeys::CAPABILITY_DISCOVERY_APPLIED => false,
			AgentToolLoopContextKeys::CAPABILITY_SOURCE_CONFIG => $capabilityDiscovery->getSourceConfig(),
			AgentToolLoopContextKeys::CAPABILITY_CATALOG => $capabilityCatalog,
			AgentToolLoopContextKeys::RESOURCE_PROVIDERS => $capabilityDiscovery->getResourceProviders(),
			AgentToolLoopContextKeys::PROMPT_PROVIDERS => $capabilityDiscovery->getPromptProviders(),
			AgentToolLoopContextKeys::MODULE_INSTRUCTIONS => $capabilityDiscovery->getInstructions(),
			AgentToolLoopContextKeys::CAPABILITY_SELECTION_CONFIG => $capabilitySelectionConfig,
			AgentToolLoopContextKeys::CAPABILITY_SELECTIONS => [],
			AgentToolLoopContextKeys::CAPABILITY_SELECTION_APPLIED => false,
			AgentToolLoopContextKeys::SELECTED_TOOL_NAMES => [],
			AgentToolLoopContextKeys::REQUIRED_TOOL_NAMES => $requiredToolNames,
			AgentToolLoopContextKeys::TOOLS => $tools,
			AgentToolLoopContextKeys::EVENT_CALLBACK => $eventCallback,
			AgentToolLoopContextKeys::LOGGER => $logger,
			AgentToolLoopContextKeys::NODE_ID => $nodeId,
			AgentToolLoopContextKeys::TRACE => $this->buildTrace($context),
			AgentToolLoopContextKeys::MAX_LOOPS => $maxLoops,
			AgentToolLoopContextKeys::BUDGET => $budget,
			AgentToolLoopContextKeys::TOOL_CACHE_CONFIG => $toolCacheConfig,
			AgentToolLoopContextKeys::RUN_STARTED_AT => hrtime(true),
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_MODEL,
			AgentToolLoopContextKeys::ITERATION => 0,
			AgentToolLoopContextKeys::CALL_INDEX => 0,
			AgentToolLoopContextKeys::PENDING_TOOL_CALLS => [],
			AgentToolLoopContextKeys::ACTIONS => [],
			AgentToolLoopContextKeys::ACTION_DECISIONS => [],
			AgentToolLoopContextKeys::ACTION_REVIEW_CANDIDATES => [],
			AgentToolLoopContextKeys::PREAPPROVED_ACTIONS => [],
			AgentToolLoopContextKeys::INTERACTION_REQUESTS => [],
			AgentToolLoopContextKeys::SUSPENSION => null,
			AgentToolLoopContextKeys::RESUME_HANDLE => '',
			AgentToolLoopContextKeys::RESUME => $resume,
			AgentToolLoopContextKeys::SUSPENDED => false,
			AgentToolLoopContextKeys::EXECUTION_STATUS => AgentExecutionStatus::RUNNING,
			AgentToolLoopContextKeys::TOOL_RESULTS => [],
			AgentToolLoopContextKeys::TOOL_CONTRACT_VALIDATIONS => [],
			AgentToolLoopContextKeys::OBSERVATIONS => [],
			AgentToolLoopContextKeys::EXECUTED_TOOL_CALLS => [],
			AgentToolLoopContextKeys::TOOL_CALL_INDEXES => [],
			AgentToolLoopContextKeys::TOOL_CACHE_PLANS => [],
			AgentToolLoopContextKeys::TOOL_CACHE_RECORDS => [],
			AgentToolLoopContextKeys::PROGRESS_ASSESSMENTS => [],
			AgentToolLoopContextKeys::CONSECUTIVE_STALLED_ITERATIONS => 0,
			AgentToolLoopContextKeys::LOOP_PROGRESS_TERMINATED => false,
			AgentToolLoopContextKeys::FINAL_ASSISTANT_MESSAGE => null,
			AgentToolLoopContextKeys::FINAL_OUTPUT_CONTENT => '',
			AgentToolLoopContextKeys::FINAL_RESPONSE_MODE => AgentToolLoopContextKeys::FINAL_RESPONSE_NONE,
			AgentToolLoopContextKeys::MODEL_RESULTS => [],
			AgentToolLoopContextKeys::CONTEXT_ASSESSMENTS => [],
			AgentToolLoopContextKeys::CONTEXT_COMPACTIONS => [],
			AgentToolLoopContextKeys::RESULT_VERIFICATIONS => [],
			AgentToolLoopContextKeys::CONTINUATION_DECISIONS => [],
			AgentToolLoopContextKeys::CONTINUATION_HINT => '',
			AgentToolLoopContextKeys::FINAL_RESPONSE_INSTRUCTION => '',
			AgentToolLoopContextKeys::TERMINAL_EVIDENCE_READY => false,
			AgentToolLoopContextKeys::BUDGET_ASSESSMENTS => [],
			AgentToolLoopContextKeys::STAGE_TRACE => [],
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::FAILURE_CODE => '',
			AgentToolLoopContextKeys::FAILURE_MESSAGE => '',
			AgentToolLoopContextKeys::FAILURE_DETAIL => []
		];

		foreach ($values as $key => $value) {
			$context->setVar($key, $value);
		}

		if ($resume !== null) {
			$this->restoreSuspensionState($context, $resume->getSuspension());
		}
	}

	private function executeStage(
		IAgentContext $context,
		IAgentStage $stage,
		?callable $eventCallback
	): void {
		$iteration = $this->getInt($context, AgentToolLoopContextKeys::ITERATION);
		$phaseBefore = (string)($context->getVar(AgentToolLoopContextKeys::PHASE) ?? '');
		$startedAt = microtime(true);

		try {
			$supported = $stage->supports($context);
		} catch (\Throwable $e) {
			$this->recordStageTrace(
				$context,
				$stage,
				$iteration,
				$phaseBefore,
				(string)($context->getVar(AgentToolLoopContextKeys::PHASE) ?? $phaseBefore),
				AgentStageTraceEntry::STATUS_FAILED,
				$this->durationMs($startedAt),
				['error' => $e->getMessage(), 'during' => 'supports']
			);
			$this->emitStageEvent($eventCallback, 'stage.error', $stage, [
				'iteration' => $iteration,
				'phase' => $phaseBefore,
				'message' => $e->getMessage(),
				'during' => 'supports'
			]);
			throw $e;
		}

		if (!$supported) {
			$this->recordStageTrace(
				$context,
				$stage,
				$iteration,
				$phaseBefore,
				(string)($context->getVar(AgentToolLoopContextKeys::PHASE) ?? $phaseBefore),
				AgentStageTraceEntry::STATUS_SKIPPED,
				$this->durationMs($startedAt),
				['reason' => 'supports_false']
			);
			return;
		}

		$this->emitStageEvent($eventCallback, 'stage.started', $stage, [
			'iteration' => $iteration,
			'phase' => $phaseBefore
		]);

		try {
			$stageResult = $stage->process($context);
			$this->applyStageResult($context, $stageResult);
			$resultMetadata = $stageResult->getMetadata();
			$phaseAfter = (string)($context->getVar(AgentToolLoopContextKeys::PHASE) ?? $phaseBefore);
			$status = $this->hasFailure($context)
				? AgentStageTraceEntry::STATUS_FAILED
				: AgentStageTraceEntry::STATUS_COMPLETED;
			$durationMs = $this->durationMs($startedAt);
			$failureCode = (string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? '');
			$traceMetadata = $resultMetadata;
			if ($failureCode !== '') {
				$traceMetadata['failure_code'] = $failureCode;
			}

			$this->recordStageTrace(
				$context,
				$stage,
				$iteration,
				$phaseBefore,
				$phaseAfter,
				$status,
				$durationMs,
				$traceMetadata
			);
			$this->emitStageEvent($eventCallback, 'stage.finished', $stage, [
				'iteration' => $iteration,
				'phase_before' => $phaseBefore,
				'phase_after' => $phaseAfter,
				'status' => $status,
				'duration_ms' => $durationMs,
				'failure_code' => $failureCode,
				'result_metadata' => $resultMetadata
			]);
		} catch (\Throwable $e) {
			$phaseAfter = (string)($context->getVar(AgentToolLoopContextKeys::PHASE) ?? $phaseBefore);
			$durationMs = $this->durationMs($startedAt);

			$this->recordStageTrace(
				$context,
				$stage,
				$iteration,
				$phaseBefore,
				$phaseAfter,
				AgentStageTraceEntry::STATUS_FAILED,
				$durationMs,
				['error' => $e->getMessage(), 'during' => 'process']
			);
			$this->emitStageEvent($eventCallback, 'stage.error', $stage, [
				'iteration' => $iteration,
				'phase_before' => $phaseBefore,
				'phase_after' => $phaseAfter,
				'duration_ms' => $durationMs,
				'message' => $e->getMessage(),
				'during' => 'process'
			]);
			throw $e;
		}
	}

	/**
	 * @param array<string,mixed> $metadata
	 */
	private function recordStageTrace(
		IAgentContext $context,
		IAgentStage $stage,
		int $iteration,
		string $phaseBefore,
		string $phaseAfter,
		string $status,
		?float $durationMs,
		array $metadata = []
	): void {
		$trace = $context->getVar(AgentToolLoopContextKeys::STAGE_TRACE);
		if (!is_array($trace)) {
			$trace = [];
		}

		$trace[] = new AgentStageTraceEntry(
			stageId: $stage->id(),
			stageName: $stage->name(),
			implementationName: $stage::getName(),
			description: $stage->getDescription(),
			aiUsage: $stage->getAiUsage(),
			iteration: $iteration,
			phaseBefore: $phaseBefore,
			phaseAfter: $phaseAfter,
			status: $status,
			durationMs: $durationMs,
			metadata: $metadata
		);
		$context->setVar(AgentToolLoopContextKeys::STAGE_TRACE, $trace);
		$this->stateSynchronizer->synchronize($context);
	}

	/**
	 * @param array<string,mixed> $payload
	 */
	private function emitStageEvent(
		?callable $eventCallback,
		string $event,
		IAgentStage $stage,
		array $payload
	): void {
		if ($eventCallback === null) {
			return;
		}

		$payload = array_merge([
			'id' => $stage->id(),
			'name' => $stage->name(),
			'implementation' => $stage::getName(),
			'description' => $stage->getDescription(),
			'ai_usage' => $stage->getAiUsage()
		], $payload);

		try {
			$eventCallback($event, $payload);
		} catch (\Throwable $e) {
			// UI event delivery must not alter the agent execution.
		}
	}

	private function durationMs(float $startedAt): float {
		return round((microtime(true) - $startedAt) * 1000, 3);
	}

	private function applyStageResult(IAgentContext $context, AgentStageResult $result): void {
		foreach ($result->getPatch() as $key => $value) {
			$context->setVar($key, $value);
		}

		$this->stateSynchronizer->synchronize($context);
	}

	private function buildResult(IAgentContext $context): AgentToolOrchestratorResult {
		$messages = $context->getVar(AgentToolLoopContextKeys::MESSAGES);
		$finalAssistantMessage = $context->getVar(AgentToolLoopContextKeys::FINAL_ASSISTANT_MESSAGE);
		$finalOutputContent = $context->getVar(AgentToolLoopContextKeys::FINAL_OUTPUT_CONTENT);
		$finalResponseMode = $context->getVar(AgentToolLoopContextKeys::FINAL_RESPONSE_MODE);
		$executedToolCalls = $context->getVar(AgentToolLoopContextKeys::EXECUTED_TOOL_CALLS);
		$failureDetail = $context->getVar(AgentToolLoopContextKeys::FAILURE_DETAIL);
		$modelResults = $context->getVar(AgentToolLoopContextKeys::MODEL_RESULTS);
		$stageTrace = $context->getVar(AgentToolLoopContextKeys::STAGE_TRACE);
		$contextCompactions = $context->getVar(AgentToolLoopContextKeys::CONTEXT_COMPACTIONS);
		$resultVerifications = $context->getVar(AgentToolLoopContextKeys::RESULT_VERIFICATIONS);
		$continuationDecisions = $context->getVar(AgentToolLoopContextKeys::CONTINUATION_DECISIONS);
		$finalResponseInstruction = $context->getVar(AgentToolLoopContextKeys::FINAL_RESPONSE_INSTRUCTION);
		$budgetAssessments = $context->getVar(AgentToolLoopContextKeys::BUDGET_ASSESSMENTS);
		$actions = $context->getVar(AgentToolLoopContextKeys::ACTIONS);
		$actionDecisions = $context->getVar(AgentToolLoopContextKeys::ACTION_DECISIONS);
		$toolCacheRecords = $context->getVar(AgentToolLoopContextKeys::TOOL_CACHE_RECORDS);
		$progressAssessments = $context->getVar(AgentToolLoopContextKeys::PROGRESS_ASSESSMENTS);
		$interactionRequests = $context->getVar(AgentToolLoopContextKeys::INTERACTION_REQUESTS);
		$resumeHandle = $context->getVar(AgentToolLoopContextKeys::RESUME_HANDLE);
		$toolContractValidations = $context->getVar(AgentToolLoopContextKeys::TOOL_CONTRACT_VALIDATIONS);
		$capabilitySelections = $context->getVar(AgentToolLoopContextKeys::CAPABILITY_SELECTIONS);
		$mutationToolNames = $context->getVar(AgentToolLoopContextKeys::MUTATION_TOOL_NAMES);
		$modelDecisionAssessments = $context->getVar(AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS);
		$toolResults = $this->collectToolResults(
			$context->getVar(AgentToolLoopContextKeys::OBSERVATIONS),
			$context->getVar(AgentToolLoopContextKeys::TOOL_RESULTS)
		);
		$executionStatus = $this->resolveExecutionStatus($context);
		$agentResult = $this->stateSynchronizer->finish($context);

		return new AgentToolOrchestratorResult(
			is_array($messages) ? $messages : [],
			is_array($finalAssistantMessage) ? $finalAssistantMessage : null,
			$this->isCompleted($context),
			$this->getInt($context, AgentToolLoopContextKeys::ITERATION),
			is_array($executedToolCalls) ? $executedToolCalls : [],
			(string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? ''),
			(string)($context->getVar(AgentToolLoopContextKeys::FAILURE_MESSAGE) ?? ''),
			is_array($failureDetail) ? $failureDetail : [],
			is_array($modelResults) ? $modelResults : [],
			is_array($stageTrace) ? $stageTrace : [],
			is_array($contextCompactions) ? $contextCompactions : [],
			is_array($resultVerifications) ? $resultVerifications : [],
			is_array($actions) ? $actions : [],
			is_array($actionDecisions) ? $actionDecisions : [],
			is_array($budgetAssessments) ? $budgetAssessments : [],
			is_scalar($finalOutputContent) ? (string)$finalOutputContent : '',
			is_scalar($finalResponseMode) ? (string)$finalResponseMode : AgentToolLoopContextKeys::FINAL_RESPONSE_NONE,
			is_array($continuationDecisions) ? $continuationDecisions : [],
			is_scalar($finalResponseInstruction) ? trim((string)$finalResponseInstruction) : '',
			is_array($toolCacheRecords) ? $toolCacheRecords : [],
			is_array($progressAssessments) ? $progressAssessments : [],
			$executionStatus,
			is_array($interactionRequests) ? $interactionRequests : [],
			is_scalar($resumeHandle) ? trim((string)$resumeHandle) : '',
			is_array($toolContractValidations) ? $toolContractValidations : [],
			is_array($capabilitySelections) ? $capabilitySelections : [],
			$agentResult,
			is_array($mutationToolNames) ? $mutationToolNames : [],
			is_array($modelDecisionAssessments) ? $modelDecisionAssessments : [],
			$toolResults
		);
	}

	/** @return array<int,AgentToolResult> */
	private function collectToolResults(mixed ...$sources): array {
		$results = [];
		$indexes = [];

		foreach ($sources as $source) {
			foreach (is_array($source) ? $source : [] as $toolResult) {
				if (!$toolResult instanceof AgentToolResult) {
					continue;
				}

				$callId = trim($toolResult->getCallId());
				$key = $callId !== ''
					? $callId
					: $toolResult->getToolName() . ':' . count($results);

				if (isset($indexes[$key])) {
					$results[$indexes[$key]] = $toolResult;
					continue;
				}

				$indexes[$key] = count($results);
				$results[] = $toolResult;
			}
		}

		return $results;
	}

	private function isCompleted(IAgentContext $context): bool {
		return $context->getVar(AgentToolLoopContextKeys::COMPLETED) === true;
	}

	private function isSuspended(IAgentContext $context): bool {
		return $context->getVar(AgentToolLoopContextKeys::SUSPENDED) === true;
	}

	private function hasFailure(IAgentContext $context): bool {
		return (string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? '') !== '';
	}

	private function getInt(IAgentContext $context, string $key): int {
		return (int)($context->getVar($key) ?? 0);
	}

	private function resolveExecutionStatus(IAgentContext $context): string {
		$status = (string)($context->getVar(AgentToolLoopContextKeys::EXECUTION_STATUS) ?? '');
		if (AgentExecutionStatus::isSuspended($status)) {
			return $status;
		}
		if ($this->hasFailure($context)) {
			return $context->getVar(AgentToolLoopContextKeys::FINAL_RESPONSE_MODE) === AgentToolLoopContextKeys::FINAL_RESPONSE_PARTIAL
				? AgentExecutionStatus::PARTIAL
				: AgentExecutionStatus::FAILED;
		}
		return $this->isCompleted($context)
			? AgentExecutionStatus::COMPLETED
			: AgentExecutionStatus::RUNNING;
	}

	private function restoreSuspensionState(IAgentContext $context, AgentSuspension $suspension): void {
		$state = $suspension->getState();
		$context->setVar(AgentToolLoopContextKeys::ITERATION, max(0, (int)($state['iteration'] ?? 0)));
		$context->setVar(AgentToolLoopContextKeys::CALL_INDEX, max(0, (int)($state['call_index'] ?? 0)));
		$context->setVar(AgentToolLoopContextKeys::MESSAGES, is_array($state['messages'] ?? null) ? $state['messages'] : []);
		$context->setVar(AgentToolLoopContextKeys::PENDING_TOOL_CALLS, $this->restoreToolCalls($state['pending_tool_calls'] ?? []));
		$context->setVar(AgentToolLoopContextKeys::ACTIONS, $this->restoreActions($state['actions'] ?? []));
		$context->setVar(AgentToolLoopContextKeys::ACTION_DECISIONS, $this->restoreActionDecisions($state['action_decisions'] ?? []));
		$context->setVar(AgentToolLoopContextKeys::TOOL_RESULTS, $this->restoreToolResults($state['tool_results'] ?? []));
		$context->setVar(AgentToolLoopContextKeys::OBSERVATIONS, $this->restoreToolResults($state['observations'] ?? []));
		$context->setVar(AgentToolLoopContextKeys::EXECUTED_TOOL_CALLS, is_array($state['executed_tool_calls'] ?? null) ? $state['executed_tool_calls'] : []);
		$context->setVar(AgentToolLoopContextKeys::TOOL_CALL_INDEXES, is_array($state['tool_call_indexes'] ?? null) ? $state['tool_call_indexes'] : []);
		$context->setVar(AgentToolLoopContextKeys::MODEL_RESULTS, is_array($state['model_results'] ?? null) ? $state['model_results'] : []);
		$context->setVar(AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS, is_array($state['model_decision_assessments'] ?? null) ? $state['model_decision_assessments'] : []);
		$context->setVar(AgentToolLoopContextKeys::SELECTED_TOOL_NAMES, is_array($state['selected_tool_names'] ?? null) ? $state['selected_tool_names'] : []);
		$context->setVar(AgentToolLoopContextKeys::CAPABILITY_SELECTION_APPLIED, (bool)($state['capability_selection_applied'] ?? false));
		$context->setVar(AgentToolLoopContextKeys::TOOL_CACHE_RECORDS, []);
		$context->setVar(AgentToolLoopContextKeys::PROGRESS_ASSESSMENTS, []);
		$context->setVar(AgentToolLoopContextKeys::SUSPENSION, $suspension);
		$context->setVar(AgentToolLoopContextKeys::INTERACTION_REQUESTS, $suspension->getRequests());
		$context->setVar(AgentToolLoopContextKeys::EXECUTION_STATUS, AgentExecutionStatus::RUNNING);
		$context->setVar(AgentToolLoopContextKeys::SUSPENDED, false);
		$context->setVar(AgentToolLoopContextKeys::COMPLETED, false);
		$context->setVar(AgentToolLoopContextKeys::FAILURE_CODE, '');
		$context->setVar(AgentToolLoopContextKeys::FAILURE_MESSAGE, '');
		$context->setVar(AgentToolLoopContextKeys::FAILURE_DETAIL, []);
	}

	/** @return array<int,AiToolCall> */
	private function restoreToolCalls(mixed $values): array {
		$result = [];
		foreach (is_array($values) ? $values : [] as $value) {
			if (is_array($value)) {
				$result[] = AiToolCall::fromArray($value);
			}
		}
		return $result;
	}

	/** @return array<int,AgentAction> */
	private function restoreActions(mixed $values): array {
		$result = [];
		foreach (is_array($values) ? $values : [] as $value) {
			if (is_array($value)) {
				$result[] = AgentAction::fromArray($value);
			}
		}
		return $result;
	}

	/** @return array<int,AgentActionDecision> */
	private function restoreActionDecisions(mixed $values): array {
		$result = [];
		foreach (is_array($values) ? $values : [] as $value) {
			if (is_array($value)) {
				$result[] = AgentActionDecision::fromArray($value);
			}
		}
		return $result;
	}

	/** @return array<int,AgentToolResult> */
	private function restoreToolResults(mixed $values): array {
		$result = [];
		foreach (is_array($values) ? $values : [] as $value) {
			if (is_array($value)) {
				$result[] = AgentToolResult::fromArray($value);
			}
		}
		return $result;
	}

	/** @param array<int,array<string,mixed>> $toolDefinitions */
	private function createFallbackCatalog(array $toolDefinitions): AgentCapabilityCatalog {
		$capabilities = [];
		foreach ($toolDefinitions as $definition) {
			if (!is_array($definition)) {
				continue;
			}
			$name = trim((string)($definition['function']['name'] ?? ''));
			if ($name === '') {
				continue;
			}
			$function = is_array($definition['function'] ?? null) ? $definition['function'] : [];
			$tags = [];
			foreach (is_array($definition['tags'] ?? null) ? $definition['tags'] : [] as $tag) {
				if (is_scalar($tag) && trim((string)$tag) !== '') {
					$tags[] = strtolower(trim((string)$tag));
				}
			}
			$capabilities[] = new AgentCapability(
				$name,
				trim((string)($definition['label'] ?? $name)),
				trim((string)($function['description'] ?? '')),
				trim((string)($definition['category'] ?? '')),
				array_values(array_unique($tags)),
				is_numeric($definition['priority'] ?? null) ? (int)$definition['priority'] : 0,
				$definition,
				'',
				'',
				$this->toBool($definition['alwaysAvailable'] ?? $definition['always_available'] ?? false)
			);
		}
		return new AgentCapabilityCatalog($capabilities);
	}


	private function toBool(mixed $value): bool {
		if (is_bool($value)) {
			return $value;
		}
		if (is_numeric($value)) {
			return (int)$value !== 0;
		}
		return in_array(strtolower(trim((string)$value)), ['true', 'yes', 'on'], true);
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

	private function log(?ILogger $logger, string $message): void {
		if ($logger === null) {
			return;
		}

		$logger->log('agenttoolorchestrator', $message);
	}

	private function logError(?ILogger $logger, string $message): void {
		$this->log($logger, '[ERROR] ' . $message);
	}
}
