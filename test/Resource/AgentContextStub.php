<?php declare(strict_types=1);

namespace MissionBay\Resource\Test;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\CanvasCloseAgentTool;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentEventSink;
use AssistantFoundation\Dto\AgentExecutionEvent;
use MissionBay\Api\IAgentTool;
use AssistantFoundation\Api\IAgentMemory;

class AgentContextStub implements IAgentContext {

	private array $vars = [];
	private IAgentMemory $memory;

	public function __construct(
		private ?IAgentEventSink $eventSink
	) {
		if ($eventSink !== null) {
			$this->vars[IAgentEventSink::CONTEXT_KEY] = $eventSink;
		}
		$this->memory = new AgentMemoryStub();
	}

	public static function getName(): string {
		return 'agentcontextstub';
	}

	public function getMemory(): IAgentMemory {
		return $this->memory;
	}

	public function setMemory(IAgentMemory $memory): void {
		$this->memory = $memory;
	}

	public function setVar(string $key, mixed $value): void {
		$this->vars[$key] = $value;
	}

	public function getVar(string $key): mixed {
		return $this->vars[$key] ?? null;
	}

	public function forgetVar(string $key): void {
		unset($this->vars[$key]);
	}

	public function listVars(): array {
		return array_keys($this->vars);
	}

}
