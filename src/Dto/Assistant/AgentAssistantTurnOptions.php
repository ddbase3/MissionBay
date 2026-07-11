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

use AssistantFoundation\Dto\AgentBudget;
use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use AssistantFoundation\Dto\AgentCapabilitySourceConfig;
use AssistantFoundation\Dto\AgentResume;
use AssistantFoundation\Dto\AgentToolCacheConfig;

final class AgentAssistantTurnOptions {

	public function __construct(
		private string $prompt,
		private string $system = 'You are a helpful assistant.',
		private int $maxToolLoops = 10,
		private bool $toolsEnabled = true,
		private bool $memoryReadEnabled = true,
		private bool $memoryWriteEnabled = true,
		private string $mode = 'chat',
		private string $nodeId = '',
		private string $assistantMessageId = '',
		private array $stageIds = [],
		private ?AgentBudget $budget = null,
		private ?AgentToolCacheConfig $toolCacheConfig = null,
		private ?AgentResume $resume = null,
		private ?AgentCapabilitySelectionConfig $capabilitySelectionConfig = null,
		private ?AgentCapabilitySourceConfig $capabilitySourceConfig = null
	) {
		$this->prompt = trim($this->prompt);
		$this->system = trim($this->system);
		$this->mode = strtolower(trim($this->mode));
		$this->nodeId = trim($this->nodeId);
		$this->assistantMessageId = trim($this->assistantMessageId);
		$this->stageIds = $this->normalizeStageIds($this->stageIds);

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


	public function getBudget(): AgentBudget {
		return $this->budget ?? AgentBudget::unlimited();
	}


	public function getToolCacheConfig(): AgentToolCacheConfig {
		return $this->toolCacheConfig ?? AgentToolCacheConfig::disabled();
	}

	public function getResume(): ?AgentResume {
		return $this->resume;
	}

	public function getCapabilitySelectionConfig(): AgentCapabilitySelectionConfig {
		return $this->capabilitySelectionConfig ?? new AgentCapabilitySelectionConfig();
	}

	public function getCapabilitySourceConfig(): AgentCapabilitySourceConfig {
		return $this->capabilitySourceConfig ?? new AgentCapabilitySourceConfig();
	}

	/**
	 * Returns the ordered configured IAgentStage component ids.
	 *
	 * An empty list selects the MissionBay default pipeline.
	 *
	 * @return array<int,string>
	 */
	public function getStageIds(): array {
		return $this->stageIds;
	}

	/**
	 * @param array<int,mixed> $stageIds
	 * @return array<int,string>
	 */
	private function normalizeStageIds(array $stageIds): array {
		$result = [];
		$known = [];

		foreach ($stageIds as $stageId) {
			if (!is_string($stageId)) {
				throw new \RuntimeException('Agent stage ids must be strings.');
			}

			$stageId = trim($stageId);
			if ($stageId === '') {
				throw new \RuntimeException('Agent stage ids must not be empty.');
			}

			if (isset($known[$stageId])) {
				throw new \RuntimeException('Duplicate agent stage id: ' . $stageId);
			}

			$known[$stageId] = true;
			$result[] = $stageId;
		}

		return $result;
	}
}
