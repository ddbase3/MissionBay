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

namespace MissionBay\Api;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentResult;
use AssistantFoundation\Dto\AgentState;

/**
 * MissionBay-specific extension of IAgentContext with typed runtime state.
 *
 * This is intentionally not a foundation slot. MissionBay owns the state
 * synchronization and result lifecycle that use this contract.
 */
interface IAgentStateContext extends IAgentContext {

	public function getState(): AgentState;

	public function setState(AgentState $state): void;

	public function isFinished(): bool;

	public function finish(AgentResult $result): void;

	public function getResult(): ?AgentResult;
}
