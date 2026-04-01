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

/**
 * Result of the non-stream tool orchestration phase.
 *
 * Important:
 * - messages contains the exact working message stack after the last tool result
 * - finalAssistantMessage stores the terminal assistant stop message from phase 1
 * - non-stream callers can directly use finalAssistantMessage as output
 */
class AgentToolOrchestratorResult {

	/**
	 * @param array<int,array<string,mixed>> $messages
	 * @param ?array<string,mixed> $finalAssistantMessage
	 * @param array<int,array<string,mixed>> $toolCalls
	 */
	public function __construct(
		private array $messages,
		private ?array $finalAssistantMessage,
		private bool $completed,
		private int $iterations,
		private array $toolCalls = []
	) {
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

	public function isCompleted(): bool {
		return $this->completed;
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
}
