<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use PHPUnit\Framework\TestCase;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentEventSink;
use AssistantFoundation\Dto\AgentExecutionEvent;
use MissionBay\Api\IAgentTool;
use MissionBay\Resource\ToolProxyAgentTool;

final class ToolProxyAgentToolTestDouble1 implements IAgentEventSink {
			private array $events;

			public function __construct(array &$events) {
				$this->events = &$events;
			}

			public function emit(AgentExecutionEvent $event): void {
				$this->events[] = [
					'event' => $event->getName(),
					'payload' => $event->getPayload()
				];
			}

			public function isCancelled(): bool {
				return false;
			}
		}
