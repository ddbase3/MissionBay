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

use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentActionDecision;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentInteractionRequest;
use AssistantFoundation\Dto\AgentBudgetAssessment;
use AssistantFoundation\Dto\AgentCapabilitySelection;
use AssistantFoundation\Dto\AgentContextCompaction;
use AssistantFoundation\Dto\AgentContinuationDecision;
use AssistantFoundation\Dto\AgentProgressAssessment;
use AssistantFoundation\Dto\AgentResultVerification;
use AssistantFoundation\Dto\AgentStageTraceEntry;
use AssistantFoundation\Dto\AgentToolContractValidation;
use AssistantFoundation\Dto\AgentToolCacheRecord;

/**
 * Result of the complete MissionBay agent stage pipeline.
 *
 * Important:
 * - messages contains the exact working message stack after the last tool result
 * - finalAssistantMessage stores the terminal model response
 * - finalOutputContent stores the response published by final-answer
 * - incomplete results still carry messages and tool calls for graceful recovery
 */
class AgentToolOrchestratorResult {

	public const FINAL_RESPONSE_NONE = 'none';
	public const FINAL_RESPONSE_COMPLETE = 'complete';
	public const FINAL_RESPONSE_PARTIAL = 'partial';

	/**
	 * @param array<int,array<string,mixed>> $messages
	 * @param ?array<string,mixed> $finalAssistantMessage
	 * @param array<int,array<string,mixed>> $toolCalls
	 * @param array<string,mixed> $failureDetail
	 * @param array<int,array<string,mixed>> $modelResults
	 * @param array<int,AgentStageTraceEntry> $stageTrace
	 * @param array<int,AgentContextCompaction> $contextCompactions
	 * @param array<int,AgentResultVerification> $resultVerifications
	 * @param array<int,AgentAction> $actions
	 * @param array<int,AgentActionDecision> $actionDecisions
	 * @param array<int,AgentBudgetAssessment> $budgetAssessments
	 * @param array<int,AgentContinuationDecision> $continuationDecisions
	 * @param array<int,AgentToolCacheRecord> $toolCacheRecords
	 * @param array<int,AgentProgressAssessment> $progressAssessments
	 * @param array<int,AgentInteractionRequest> $interactionRequests
	 * @param array<int,AgentToolContractValidation> $toolContractValidations
	 * @param array<int,AgentCapabilitySelection> $capabilitySelections
	 */
	public function __construct(
		private array $messages,
		private ?array $finalAssistantMessage,
		private bool $completed,
		private int $iterations,
		private array $toolCalls = [],
		private string $failureCode = '',
		private string $failureMessage = '',
		private array $failureDetail = [],
		private array $modelResults = [],
		private array $stageTrace = [],
		private array $contextCompactions = [],
		private array $resultVerifications = [],
		private array $actions = [],
		private array $actionDecisions = [],
		private array $budgetAssessments = [],
		private string $finalOutputContent = '',
		private string $finalResponseMode = self::FINAL_RESPONSE_NONE,
		private array $continuationDecisions = [],
		private string $finalResponseInstruction = '',
		private array $toolCacheRecords = [],
		private array $progressAssessments = [],
		private string $executionStatus = AgentExecutionStatus::RUNNING,
		private array $interactionRequests = [],
		private string $resumeHandle = '',
		private array $toolContractValidations = [],
		private array $capabilitySelections = []
	) {
		if (!in_array($this->executionStatus, AgentExecutionStatus::all(), true)) {
			throw new \InvalidArgumentException('Unsupported execution status: ' . $this->executionStatus);
		}

		foreach ($this->interactionRequests as $request) {
			if (!$request instanceof AgentInteractionRequest) {
				throw new \InvalidArgumentException('Interaction requests must contain only AgentInteractionRequest instances.');
			}
		}

		foreach ($this->toolContractValidations as $validation) {
			if (!$validation instanceof AgentToolContractValidation) {
				throw new \InvalidArgumentException('Tool contract validations must contain only AgentToolContractValidation instances.');
			}
		}

		foreach ($this->capabilitySelections as $selection) {
			if (!$selection instanceof AgentCapabilitySelection) {
				throw new \InvalidArgumentException('Capability selections must contain only AgentCapabilitySelection instances.');
			}
		}

		if (AgentExecutionStatus::isSuspended($this->executionStatus)) {
			if (trim($this->resumeHandle) === '' || $this->interactionRequests === []) {
				throw new \InvalidArgumentException('Suspended orchestration results require a resume handle and interaction requests.');
			}
		}
		if (!in_array($this->finalResponseMode, [
			self::FINAL_RESPONSE_NONE,
			self::FINAL_RESPONSE_COMPLETE,
			self::FINAL_RESPONSE_PARTIAL
		], true)) {
			throw new \InvalidArgumentException('Unsupported final response mode: ' . $this->finalResponseMode);
		}
	}

	/**
	 * Returns the working messages after the tool phase.
	 *
	 * These messages include:
	 * - system messages
	 * - visible dialogue history
	 * - assistant tool-call messages
	 * - tool result messages
	 *
	 * The terminal assistant stop message is intentionally not included here.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function getMessages(): array {
		return $this->messages;
	}

	/**
	 * Returns the terminal assistant message from phase 1, if any.
	 *
	 * @return ?array<string,mixed>
	 */
	public function getFinalAssistantMessage(): ?array {
		return $this->finalAssistantMessage;
	}


	/**
	 * Returns the visible answer produced by the final-answer stage.
	 */
	public function getFinalOutputContent(): string {
		return $this->finalOutputContent;
	}

	public function isCompleted(): bool {
		return $this->completed;
	}

	public function getFinalResponseMode(): string {
		return $this->finalResponseMode;
	}

	public function canGenerateFinalResponse(): bool {
		if ($this->isSuspended()) {
			return false;
		}

		if ($this->finalResponseMode === self::FINAL_RESPONSE_COMPLETE) {
			return $this->completed && !$this->hasFailure();
		}

		if ($this->finalResponseMode === self::FINAL_RESPONSE_PARTIAL) {
			return $this->messages !== [];
		}

		return false;
	}

	public function isPartialFinalResponse(): bool {
		return $this->finalResponseMode === self::FINAL_RESPONSE_PARTIAL;
	}

	public function getIterations(): int {
		return $this->iterations;
	}

	/**
	 * Returns the executed tool calls in a simple debug-friendly structure.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function getToolCalls(): array {
		return $this->toolCalls;
	}

	public function getFailureCode(): string {
		return $this->failureCode;
	}

	public function getFailureMessage(): string {
		return $this->failureMessage;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getFailureDetail(): array {
		return $this->failureDetail;
	}

	/**
	 * Returns normalized metadata for every model call performed by the loop.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function getModelResults(): array {
		return $this->modelResults;
	}

	/**
	 * Returns one trace entry for every configured stage decision.
	 *
	 * Supported stages are recorded as completed or failed. Stages whose
	 * supports() method returned false are recorded as skipped.
	 *
	 * @return array<int,AgentStageTraceEntry>
	 */
	public function getStageTrace(): array {
		return $this->stageTrace;
	}

	/**
	 * Returns all attempted context compactions performed during the run.
	 *
	 * @return array<int,AgentContextCompaction>
	 */
	public function getContextCompactions(): array {
		return $this->contextCompactions;
	}

	/**
	 * Returns deterministic and semantic result verification records.
	 *
	 * @return array<int,AgentResultVerification>
	 */
	public function getResultVerifications(): array {
		return $this->resultVerifications;
	}

	/**
	 * Returns the semantic actions proposed during the run.
	 *
	 * @return array<int,AgentAction>
	 */
	public function getActions(): array {
		return $this->actions;
	}

	/**
	 * Returns every configured policy decision in evaluation order.
	 *
	 * @return array<int,AgentActionDecision>
	 */
	public function getActionDecisions(): array {
		return $this->actionDecisions;
	}


	/**
	 * Returns every model and tool budget checkpoint recorded by the run.
	 *
	 * @return array<int,AgentBudgetAssessment>
	 */
	public function getBudgetAssessments(): array {
		return $this->budgetAssessments;
	}


	/**
	 * Returns deterministic loop-control decisions in execution order.
	 *
	 * @return array<int,AgentContinuationDecision>
	 */
	public function getContinuationDecisions(): array {
		return $this->continuationDecisions;
	}

	/**
	 * Returns an optional transient instruction for the final response request.
	 */
	public function getFinalResponseInstruction(): string {
		return $this->finalResponseInstruction;
	}


	/**
	 * Returns cache lookup and store decisions in execution order.
	 *
	 * @return array<int,AgentToolCacheRecord>
	 */
	public function getToolCacheRecords(): array {
		return $this->toolCacheRecords;
	}

	/**
	 * Returns deterministic loop progress assessments.
	 *
	 * @return array<int,AgentProgressAssessment>
	 */
	public function getProgressAssessments(): array {
		return $this->progressAssessments;
	}

	public function getExecutionStatus(): string {
		return $this->executionStatus;
	}

	public function isSuspended(): bool {
		return AgentExecutionStatus::isSuspended($this->executionStatus);
	}

	public function isAwaitingApproval(): bool {
		return $this->executionStatus === AgentExecutionStatus::AWAITING_APPROVAL;
	}

	public function isAwaitingInput(): bool {
		return $this->executionStatus === AgentExecutionStatus::AWAITING_INPUT;
	}

	/** @return array<int,AgentInteractionRequest> */
	public function getInteractionRequests(): array {
		return $this->interactionRequests;
	}

	public function getResumeHandle(): string {
		return $this->resumeHandle;
	}

	/** @return array<int,AgentToolContractValidation> */
	public function getToolContractValidations(): array {
		return $this->toolContractValidations;
	}

	/** @return array<int,AgentCapabilitySelection> */
	public function getCapabilitySelections(): array {
		return $this->capabilitySelections;
	}

	public function hasFailure(): bool {
		return $this->failureCode !== '' || $this->failureMessage !== '';
	}
}
