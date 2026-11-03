<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\HelloWorldCanvasAgentTool;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentEventSink;
use AssistantFoundation\Dto\AgentExecutionEvent;
use AssistantFoundation\Api\IAgentMemory;

final class HelloWorldCanvasAgentToolTestDouble3 implements IAgentEventSink {
			public array $events = [];
			private bool $cancelled;
			private bool $throwOnEmit;

			public function __construct(bool $cancelled, bool $throwOnEmit) {
				$this->cancelled = $cancelled;
				$this->throwOnEmit = $throwOnEmit;
			}

			public function emit(AgentExecutionEvent $event): void {
				if ($this->throwOnEmit) {
					throw new \RuntimeException('push failed');
				}
				$this->events[] = [
					'event' => $event->getName(),
					'data' => $event->getPayload()
				];
			}

			public function isCancelled(): bool {
				return $this->cancelled;
			}
		}
