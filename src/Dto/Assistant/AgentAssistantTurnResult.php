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
use MissionBay\Orchestrator\AgentToolOrchestratorResult;

final class AgentAssistantTurnResult {

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

	public function getFallbackContent(): ?string {
		return $this->fallbackContent;
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
