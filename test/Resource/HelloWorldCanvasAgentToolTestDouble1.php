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
use Base3\MissionBay\Resource\HelloWorldCanvasAgentTool;
use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentEventSink;
use Base3\AssistantFoundation\Dto\AgentExecutionEvent;
use Base3\AssistantFoundation\Api\IAgentMemory;

final class HelloWorldCanvasAgentToolTestDouble1 implements IAgentMemory  {
			private array $history = [];

			public static function getName(): string {
				return 'testagentmemory';
			}

			public function loadNodeHistory(string $nodeId): array {
				return $this->history[$nodeId] ?? [];
			}

			public function appendNodeHistory(string $nodeId, array $message): void {
				$this->history[$nodeId] ??= [];
				$this->history[$nodeId][] = $message;
			}

			public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
				if (empty($this->history[$nodeId])) {
					return false;
				}

				foreach ($this->history[$nodeId] as &$msg) {
					if (($msg['id'] ?? null) === $messageId) {
						$msg['feedback'] = $feedback;
						return true;
					}
				}

				return false;
			}

			public function resetNodeHistory(string $nodeId): void {
				$this->history[$nodeId] = [];
			}

			public function getPriority(): int {
				return 0;
			}
		}
