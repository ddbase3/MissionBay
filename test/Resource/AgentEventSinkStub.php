<?php declare(strict_types=1);

namespace MissionBay\Resource\Test;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\CanvasCloseAgentTool;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentEventSink;
use AssistantFoundation\Dto\AgentExecutionEvent;
use MissionBay\Api\IAgentTool;
use AssistantFoundation\Api\IAgentMemory;

class AgentEventSinkStub implements IAgentEventSink {

	public bool $disconnected = false;
	public bool $throwOnPush = false;

	/** @var array<int, array{0:string,1:array<string,mixed>}> */
	public array $pushed = [];

	public function emit(AgentExecutionEvent $event): void {
		if ($this->throwOnPush) {
			throw new \RuntimeException('push failed');
		}
		$this->pushed[] = [$event->getName(), $event->getPayload()];
	}

	public function isCancelled(): bool {
		return $this->disconnected;
	}

}
