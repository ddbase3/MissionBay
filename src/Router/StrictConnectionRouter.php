<?php declare(strict_types=1);

namespace MissionBay\Router;

use MissionBay\Api\IAgentRouter;
use MissionBay\Api\IAgentContext;

class StrictConnectionRouter implements IAgentRouter {

	private array $connections = [];
	private array $initialInputs = [];
	private array $mandatoryConnections = [];

	public static function getName(): string {
		return 'strictconnectionrouter';
	}

	public function getNextNode(string $currentNodeId, array $output, IAgentContext $context): ?string {
		foreach ($this->connections as $conn) {
			if ($conn['fromNode'] === $currentNodeId) {
				return $conn['toNode'];
			}
		}
		return null;
	}

	public function mapInputs(string $fromNodeId, string $toNodeId, array $output, IAgentContext $context): array {
		$mapped = [];
		foreach ($this->connections as $conn) {
			if ($conn['fromNode'] === $fromNodeId && $conn['toNode'] === $toNodeId) {
				$fromKey = $conn['fromOutput'];
				$toKey = $conn['toInput'];
				$mapped[$toKey] = $output[$fromKey] ?? null;
			}
		}
		return $mapped;
	}

	public function isReady(string $nodeId, array $currentInputs, IAgentContext $context): bool {
		// Wenn keine eingehenden Verbindungen vorhanden sind, ist der Node startbereit
		$hasIncoming = false;
		foreach ($this->connections as $conn) {
			if ($conn['toNode'] === $nodeId) {
				$hasIncoming = true;
				if (!array_key_exists($conn['toInput'], $currentInputs)) {
					return false;
				}
			}
		}
		return !$hasIncoming || true;
	}

	public function getInitialInputs(): array {
		return $this->initialInputs;
	}

	public function getConnections(): array {
		return $this->connections;
	}

	public function addConnection(string $fromNode, string $fromOutput, string $toNode, string $toInput, bool $mandatory = false): void {
		$this->connections[] = [
			'fromNode' => $fromNode,
			'fromOutput' => $fromOutput,
			'toNode' => $toNode,
			'toInput' => $toInput,
			'mandatory' => $mandatory
		];
		if ($mandatory) {
			$this->mandatoryConnections[] = "$fromNode.$fromOutput->$toNode.$toInput";
		}
	}

	public function addInitialInput(string $nodeId, string $key, mixed $value): void {
		$this->initialInputs[$nodeId][$key] = $value;
	}
}

