<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\HelloWorldCanvasAgentTool;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentEventSink;
use AssistantFoundation\Dto\AgentExecutionEvent;
use AssistantFoundation\Api\IAgentMemory;

final class HelloWorldCanvasAgentToolTestDouble2 implements IAgentContext {
			private array $vars;
			private IAgentMemory $memory;

			public function __construct(array $vars, IAgentMemory $memory) {
				$this->vars = $vars;
				$this->memory = $memory;
			}

			public static function getName(): string {
				return 'testagentcontext';
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
