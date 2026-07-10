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

use AssistantFoundation\Api\IAiChatModel;
use Base3\Logger\Api\ILogger;
use AssistantFoundation\Api\IAgentMemory;
use MissionBay\Api\IAgentProfileSelector;
use MissionBay\Api\IAgentTool;

final class AgentAssistantTurnResources {

	/**
	 * @param array<int,IAgentMemory> $memories
	 * @param array<int,IAgentTool> $tools
	 */
	public function __construct(
		private IAiChatModel $model,
		private array $memories = [],
		private array $tools = [],
		private ?ILogger $logger = null,
		private ?IAgentProfileSelector $profileSelector = null
	) {
	}

	public function getModel(): IAiChatModel {
		return $this->model;
	}

	/**
	 * @return array<int,IAgentMemory>
	 */
	public function getMemories(): array {
		return $this->memories;
	}

	/**
	 * @return array<int,IAgentTool>
	 */
	public function getTools(): array {
		return $this->tools;
	}

	public function getLogger(): ?ILogger {
		return $this->logger;
	}

	public function getProfileSelector(): ?IAgentProfileSelector {
		return $this->profileSelector;
	}
}
