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

use AssistantFoundation\Api\IAiChatModel;
use MissionBay\Dto\Assistant\AgentAssistantTurnResult;

interface IAgentAssistantFinalResponseService {

	public function createDirectResponse(IAiChatModel $model, AgentAssistantTurnResult $turnResult): string;

	/**
	 * @param callable $onData function(string $delta): void
	 * @param ?callable $onMeta function(array $meta): void
	 */
	public function createStreamingResponse(IAiChatModel $model, AgentAssistantTurnResult $turnResult, callable $onData, ?callable $onMeta = null): string;

	/**
	 * @return array<string,mixed>
	 */
	public function createAssistantMessage(AgentAssistantTurnResult $turnResult, string $content): array;
}
