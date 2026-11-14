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

namespace Base3\MissionBay\Test\Assistant;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Dto\AiChatResult;
use Base3\AssistantFoundation\Dto\AiResultMetadata;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MissionBay\Api\IAgentAssistantFallbackBuilder;
use Base3\MissionBay\Api\IAgentAssistantFinalResponseService;
use Base3\MissionBay\Api\IAgentAssistantMemoryService;
use Base3\MissionBay\Api\IAgentAssistantTurnService;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Dto\Assistant\AgentAssistantTurnOptions;
use Base3\MissionBay\Dto\Assistant\AgentAssistantTurnResources;
use Base3\MissionBay\Dto\Assistant\AgentAssistantTurnResult;
use Base3\MissionBay\Node\Ai\AiAssistantNode;
use Base3\MissionBay\Orchestrator\AgentToolOrchestratorResult;
use PHPUnit\Framework\TestCase;

final class SuggestionsChatModelDouble implements IAiChatModel {

	public function complete(array $messages, array $tools = []): AiChatResult {
		return new AiChatResult('Suggestions', [], new AiResultMetadata('chat'));
	}

	public function chat(array $messages): string {
		return 'Suggestions';
	}

	public function raw(array $messages, array $tools = []): mixed {
		return [];
	}

	public function streamResult(
		array $messages,
		array $tools,
		callable $onData,
		?callable $onMeta = null
	): AiChatResult {
		return $this->complete($messages, $tools);
	}

	public function stream(array $messages, array $tools, callable $onData, ?callable $onMeta = null): void {
		$onData('Suggestions');
	}

	public function setOptions(array $options): void {
	}

	public function getOptions(): array {
		return [];
	}
}
