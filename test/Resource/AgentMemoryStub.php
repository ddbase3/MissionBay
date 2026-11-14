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

namespace Base3\MissionBay\Test\Resource;

use PHPUnit\Framework\TestCase;
use Base3\MissionBay\Resource\CanvasCloseAgentTool;
use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentEventSink;
use Base3\AssistantFoundation\Dto\AgentExecutionEvent;
use Base3\MissionBay\Api\IAgentTool;
use Base3\AssistantFoundation\Api\IAgentMemory;

class AgentMemoryStub implements IAgentMemory {

	public static function getName(): string {
		return 'agentmemorystub';
	}

	public function loadNodeHistory(string $nodeId): array {
		return [];
	}

	public function appendNodeHistory(string $nodeId, array $message): void {
		return;
	}

	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
		return false;
	}

	public function resetNodeHistory(string $nodeId): void {
		return;
	}

	public function getPriority(): int {
		return 0;
	}

}
