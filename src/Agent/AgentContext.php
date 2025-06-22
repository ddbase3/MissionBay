<?php declare(strict_types=1);

namespace MissionBay\Agent;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentMemory;

class AgentContext implements IAgentContext {

	private IAgentMemory $memory;
	private array $vars = [];

	public function __construct(IAgentMemory $memory, array $vars = []) {
		$this->memory = $memory;
		$this->vars = $vars;
	}

	/*
	public function loadHistory(string $userId): array {
		return $this->memory->load($userId);
	}

	public function remember(string $userId, string $role, string $msg): void {
		$this->memory->remember($userId, $role, $msg);
	}
	 */

	public function getMemory(): IAgentMemory {
		return $this->memory;
	}

	public function setVar(string $key, mixed $value): void {
		$this->vars[$key] = $value;
	}

	public function getVar(string $key): mixed {
		return $this->vars[$key] ?? null;
	}
}

