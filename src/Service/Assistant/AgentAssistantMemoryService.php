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

namespace MissionBay\Service\Assistant;

use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentAssistantMemoryService;
use MissionBay\Api\IAgentAssistantMessageFactory;
use MissionBay\Api\IAgentMemory;

final class AgentAssistantMemoryService implements IAgentAssistantMemoryService {

	public function __construct(private IAgentAssistantMessageFactory $messageFactory) {
	}

	public function sortMemories(array $memories): array {
		usort($memories, fn(IAgentMemory $a, IAgentMemory $b) => $a->getPriority() <=> $b->getPriority());
		return $memories;
	}

	public function buildInitialMessages(string $system, array $memories, string $nodeId, ?ILogger $logger = null): array {
		$messages = [
			['role' => 'system', 'content' => $system]
		];

		foreach ($memories as $memory) {
			foreach ($this->safeLoadHistory($memory, $nodeId, $logger) as $entry) {
				if (!$this->messageFactory->isVisibleHistoryEntry($entry)) {
					continue;
				}

				$messages[] = $entry;
			}
		}

		return $messages;
	}

	public function appendVisibleMessage(array $memories, string $nodeId, array $message, ?ILogger $logger = null): void {
		foreach ($memories as $memory) {
			$this->safeAppendHistory($memory, $nodeId, $message, $logger);
		}
	}

	private function safeLoadHistory(IAgentMemory $memory, string $nodeId, ?ILogger $logger): array {
		try {
			return $memory->loadNodeHistory($nodeId) ?? [];
		} catch (\Throwable $e) {
			$this->logError($logger, 'Memory loadNodeHistory failed: ' . $e->getMessage());
			return [];
		}
	}

	private function safeAppendHistory(IAgentMemory $memory, string $nodeId, array $message, ?ILogger $logger): void {
		try {
			$memory->appendNodeHistory($nodeId, $message);
		} catch (\Throwable $e) {
			$this->logError($logger, 'Memory appendNodeHistory failed: ' . $e->getMessage());
		}
	}

	private function logError(?ILogger $logger, string $message): void {
		if ($logger === null) {
			return;
		}

		$logger->log('agentassistantmemoryservice', '[ERROR] ' . $message);
	}
}
