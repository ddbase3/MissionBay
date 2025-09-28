<?php declare(strict_types=1);

namespace MissionBay\Context;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentMemory;
use MissionBay\Router\StrictConnectionRouter;
use MissionBay\Memory\NoMemory;

class AgentContext implements IAgentContext {

	private IAgentMemory $memory;
	private array $vars;
	private array $resources = [];

	public function __construct(
		?IAgentMemory $memory = null,
		array $vars = []
	) {
		$this->memory = $memory ?? new NoMemory();
		$this->vars = $vars;
	}

	public static function getName(): string {
		return 'agentcontext';
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

