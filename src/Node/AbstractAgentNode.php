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

namespace MissionBay\Node;

use MissionBay\Api\IAgentNode;
use AssistantFoundation\Api\IAgentContext;
use MissionBay\Agent\AgentNodeDock;

abstract class AbstractAgentNode implements IAgentNode {

	protected string $id;
	protected array $config = [];

	public function __construct(?string $id = null) {
		$this->id = $id ?? uniqid('node_', true);
	}

	public function getId(): string {
		return $this->id;
	}

	public function setId(string $id): void {
		$this->id = $id;
	}

	public function getConfig(): array {
		return $this->config;
	}

	public function setConfig(array $config): void {
		$this->config = $config;
	}

	public function getDockDefinitions(): array {
		return [];
	}

	abstract public static function getName(): string;

	abstract public function getInputDefinitions(): array;

	abstract public function getOutputDefinitions(): array;

	abstract public function execute(array $inputs, array $resources, IAgentContext $context): array;

	abstract public function getDescription(): string;

	protected function error($message): string {
		return '[ ' . static::getName() . ' | ' . $this->id . ' ] ' . $message;
	}
}

