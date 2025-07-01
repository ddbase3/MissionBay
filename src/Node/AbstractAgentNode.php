<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentNode;
use MissionBay\Api\IAgentContext;

abstract class AbstractAgentNode implements IAgentNode {

	protected string $id;

	public function __construct(?string $id = null) {
		$this->id = $id ?? uniqid('node_', true);
	}

	public function getId(): string {
		return $this->id;
	}

	public function setId(string $id): void {
		$this->id = $id;
	}

	abstract public static function getName(): string;

	abstract public function getInputDefinitions(): array;

	abstract public function getOutputDefinitions(): array;

	abstract public function execute(array $inputs, IAgentContext $context): array;

	abstract public function getDescription(): string;

	protected function error($message): string {
		return '[ ' . static::getName() . ' | ' . $this->id . ' ] ' . $message;
	}
}

