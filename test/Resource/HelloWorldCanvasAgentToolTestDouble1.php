<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\HelloWorldCanvasAgentTool;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentEventSink;
use AssistantFoundation\Dto\AgentExecutionEvent;
use AssistantFoundation\Api\IAgentMemory;

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
