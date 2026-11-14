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
