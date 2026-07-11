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

namespace MissionBay\Dto\Assistant;

use AssistantFoundation\Api\IAgentMemory;
use AssistantFoundation\Dto\AiResultMetadata;
use MissionBay\Orchestrator\AgentToolOrchestratorResult;

final class AgentAssistantTurnResult {

	/** @var array<int,array<string,mixed>> */
	private array $modelResults = [];

	/**
	 * @param array<int,array<string,mixed>> $messages
	 * @param array<string,mixed> $userMessage
	 * @param array<int,IAgentMemory> $memories
	 */
	public function __construct(
		private array $messages,
		private array $userMessage,
		private array $memories,
		private string $nodeId,
		private string $assistantMessageId,
		private bool $memoryWriteEnabled,
		private ?AgentToolOrchestratorResult $orchestrationResult = null,
		private bool $completed = true,
		private ?string $fallbackContent = null
	) {
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function getMessages(): array {
		return $this->messages;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getUserMessage(): array {
		return $this->userMessage;
	}

	/**
	 * @return array<int,IAgentMemory>
	 */
	public function getMemories(): array {
		return $this->memories;
	}

	public function getNodeId(): string {
		return $this->nodeId;
	}

	public function getAssistantMessageId(): string {
		return $this->assistantMessageId;
	}

	public function shouldWriteMemory(): bool {
		return $this->memoryWriteEnabled;
	}

	public function getOrchestrationResult(): ?AgentToolOrchestratorResult {
		return $this->orchestrationResult;
	}

	public function isCompleted(): bool {
		return $this->completed;
	}

	public function canGenerateFinalResponse(): bool {
		if ($this->orchestrationResult === null) {
			return $this->completed;
		}

		return $this->orchestrationResult->canGenerateFinalResponse();
	}

	public function isPartialFinalResponse(): bool {
		return $this->orchestrationResult?->isPartialFinalResponse() ?? false;
	}

	public function getFallbackContent(): ?string {
		return $this->fallbackContent;
	}

	public function getFinalOutputContent(): string {
		return $this->orchestrationResult?->getFinalOutputContent() ?? '';
	}


	public function addModelResult(AiResultMetadata $metadata): void {
		$this->modelResults[] = $metadata->toArray();
	}

	/**
	 * Returns normalized metadata for all non-streaming model calls in this turn.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function getModelResults(): array {
		$orchestratorResults = $this->orchestrationResult?->getModelResults() ?? [];

		return array_merge($orchestratorResults, $this->modelResults);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function getToolCalls(): array {
		if ($this->orchestrationResult === null) {
			return [];
		}

		return $this->orchestrationResult->getToolCalls();
	}

	public function getIterations(): int {
		if ($this->orchestrationResult === null) {
			return 0;
		}

		return $this->orchestrationResult->getIterations();
	}

	/**
	 * @return array<int,\AssistantFoundation\Dto\AgentStageTraceEntry>
	 */
	public function getStageTrace(): array {
		return $this->orchestrationResult?->getStageTrace() ?? [];
	}

	/**
	 * @return array<int,\AssistantFoundation\Dto\AgentContextCompaction>
	 */
	public function getContextCompactions(): array {
		return $this->orchestrationResult?->getContextCompactions() ?? [];
	}

	/**
	 * @return array<int,\AssistantFoundation\Dto\AgentResultVerification>
	 */
	public function getResultVerifications(): array {
		return $this->orchestrationResult?->getResultVerifications() ?? [];
	}



	/**
	 * @return array<int,\AssistantFoundation\Dto\AgentContinuationDecision>
	 */
	public function getContinuationDecisions(): array {
		return $this->orchestrationResult?->getContinuationDecisions() ?? [];
	}

	/**
	 * @return array<int,\AssistantFoundation\Dto\AgentProgressAssessment>
	 */
	public function getProgressAssessments(): array {
		return $this->orchestrationResult?->getProgressAssessments() ?? [];
	}

	public function getFinalResponseInstruction(): string {
		return $this->orchestrationResult?->getFinalResponseInstruction() ?? '';
	}

	/**
	 * @return array<int,\AssistantFoundation\Dto\AgentBudgetAssessment>
	 */
	public function getBudgetAssessments(): array {
		return $this->orchestrationResult?->getBudgetAssessments() ?? [];
	}

	public function getFailureCode(): string {
		if ($this->orchestrationResult === null) {
			return '';
		}

		return $this->orchestrationResult->getFailureCode();
	}

	public function getFailureMessage(): string {
		if ($this->orchestrationResult === null) {
			return '';
		}

		return $this->orchestrationResult->getFailureMessage();
	}
}
