<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentNode;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentFlow;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Agent\AgentNodePort;

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

	abstract public function execute(array $inputs, array $resources, IAgentContext $context, IAgentFlow $flow): array;

	abstract public function getDescription(): string;

	protected function error($message): string {
		return '[ ' . static::getName() . ' | ' . $this->id . ' ] ' . $message;
	}
}
