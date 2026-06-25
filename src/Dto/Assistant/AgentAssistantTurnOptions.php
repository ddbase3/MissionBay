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

final class AgentAssistantTurnOptions {

	public function __construct(
		private string $prompt,
		private string $system = 'You are a helpful assistant.',
		private int $maxToolLoops = 8,
		private bool $toolsEnabled = true,
		private bool $memoryReadEnabled = true,
		private bool $memoryWriteEnabled = true,
		private string $mode = 'chat',
		private string $nodeId = '',
		private string $assistantMessageId = ''
	) {
		$this->prompt = trim($this->prompt);
		$this->system = trim($this->system);
		$this->mode = strtolower(trim($this->mode));
		$this->nodeId = trim($this->nodeId);
		$this->assistantMessageId = trim($this->assistantMessageId);

		if ($this->system === '') {
			$this->system = 'You are a helpful assistant.';
		}

		if ($this->mode === '') {
			$this->mode = 'chat';
		}

		if ($this->maxToolLoops < 1) {
			throw new \RuntimeException('Max tool loops must be greater than zero.');
		}

		if ($this->assistantMessageId === '') {
			$this->assistantMessageId = uniqid('msg_', true);
		}
	}

	public function getPrompt(): string {
		return $this->prompt;
	}

	public function getSystem(): string {
		return $this->system;
	}

	public function getMaxToolLoops(): int {
		return $this->maxToolLoops;
	}

	public function areToolsEnabled(): bool {
		return $this->toolsEnabled;
	}

	public function isMemoryReadEnabled(): bool {
		return $this->memoryReadEnabled;
	}

	public function isMemoryWriteEnabled(): bool {
		return $this->memoryWriteEnabled;
	}

	public function getMode(): string {
		return $this->mode;
	}

	public function getNodeId(): string {
		return $this->nodeId;
	}

	public function getAssistantMessageId(): string {
		return $this->assistantMessageId;
	}
}
