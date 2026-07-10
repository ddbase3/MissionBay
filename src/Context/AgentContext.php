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

namespace MissionBay\Context;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentMemory;
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

