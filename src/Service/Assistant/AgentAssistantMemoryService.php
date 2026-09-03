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

use AssistantFoundation\Api\IAgentMemory;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentAssistantMemoryService;
use MissionBay\Api\IAgentAssistantMessageFactory;
use MissionBay\Api\IAgentMemoryRoleResolver;

final class AgentAssistantMemoryService implements IAgentAssistantMemoryService {

	private const MAX_AGENT_HISTORY_MESSAGES = 20;

	public function __construct(
		private readonly IAgentAssistantMessageFactory $messageFactory,
		private readonly IAgentMemoryRoleResolver $roleResolver
	) {
	}

	public function sortMemories(array $memories): array {
		$memories = $this->deduplicateMemories($memories);

		usort($memories, function(IAgentMemory $left, IAgentMemory $right): int {
			$result = $left->getPriority() <=> $right->getPriority();
			if ($result !== 0) {
				return $result;
			}

			$result = strcmp($left::class, $right::class);
			if ($result !== 0) {
				return $result;
			}

			return strcmp($this->memoryIdentity($left), $this->memoryIdentity($right));
		});

		return $memories;
	}

	public function buildInitialMessages(string $system, array $memories, string $nodeId, ?ILogger $logger = null): array {
		$history = [];

		foreach ($memories as $memory) {
			if (!$memory instanceof IAgentMemory || !$this->roleResolver->isConversationMemory($memory)) {
				continue;
			}

			foreach ($this->safeLoadHistory($memory, $nodeId, $logger) as $entry) {
				if (!$this->messageFactory->isVisibleHistoryEntry($entry)) {
					continue;
				}

				$history[] = $entry;
			}
		}

		return array_merge(
			[['role' => 'system', 'content' => $system]],
			array_slice($history, -self::MAX_AGENT_HISTORY_MESSAGES)
		);
	}

	public function appendVisibleMessage(array $memories, string $nodeId, array $message, ?ILogger $logger = null): void {
		foreach ($memories as $memory) {
			if (!$memory instanceof IAgentMemory || !$this->roleResolver->isConversationMemory($memory)) {
				continue;
			}

			$this->safeAppendHistory($memory, $nodeId, $message, $logger);
		}
	}

	/**
	 * @param array<int,IAgentMemory> $memories
	 * @return array<int,IAgentMemory>
	 */
	private function deduplicateMemories(array $memories): array {
		$result = [];
		$seen = [];

		foreach ($memories as $memory) {
			if (!$memory instanceof IAgentMemory || !$this->roleResolver->isConversationMemory($memory)) {
				continue;
			}

			$objectId = spl_object_id($memory);
			if (isset($seen[$objectId])) {
				continue;
			}

			$seen[$objectId] = true;
			$result[] = $memory;
		}

		return $result;
	}

	private function memoryIdentity(IAgentMemory $memory): string {
		return (string)spl_object_id($memory);
	}

	private function safeLoadHistory(IAgentMemory $memory, string $nodeId, ?ILogger $logger): array {
		try {
			return $memory->loadNodeHistory($nodeId) ?? [];
		}
		catch (\Throwable $e) {
			$this->logError($logger, 'Conversation memory loadNodeHistory failed for ' . $memory::class . ': ' . $e->getMessage());
			return [];
		}
	}

	private function safeAppendHistory(IAgentMemory $memory, string $nodeId, array $message, ?ILogger $logger): void {
		try {
			$memory->appendNodeHistory($nodeId, $message);
		}
		catch (\Throwable $e) {
			$this->logError($logger, 'Conversation memory appendNodeHistory failed for ' . $memory::class . ': ' . $e->getMessage());
		}
	}

	private function logError(?ILogger $logger, string $message): void {
		if ($logger === null) {
			return;
		}

		$logger->log('agentassistantmemoryservice', '[ERROR] ' . $message);
	}
}
