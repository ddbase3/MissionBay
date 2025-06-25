<?php declare(strict_types=1);

namespace MissionBay\Context;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentRouter;
use MissionBay\Api\IAgentMemory;
use MissionBay\Router\StrictConnectionRouter;
use MissionBay\Memory\NoMemory;

class AgentContext implements IAgentContext {

	private IAgentRouter $router;
	private IAgentMemory $memory;
	private array $vars;

	public function __construct(
		?IAgentRouter $router = null,
		?IAgentMemory $memory = null,
		array $vars = []
	) {
		$this->router = $router ?? new StrictConnectionRouter();
		$this->memory = $memory ?? new NoMemory();
		$this->vars = $vars;
	}

	public static function getName(): string {
		return 'agentcontext';
	}

	public function getRouter(): IAgentRouter {
		return $this->router;
	}

	public function setRouter(IAgentRouter $router): void {
		$this->router = $router;
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
}

