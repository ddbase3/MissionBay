<?php declare(strict_types=1);

namespace MissionBay\Agent;

use MissionBay\Api\IAgentFlow;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentNode;
use MissionBay\Api\IAgentNodeFactory;

class AgentFlow implements IAgentFlow {

	private array $nodes = [];
	private array $connections = [];
	private array $initialInputs = [];

	public function __construct(private readonly IAgentNodeFactory $agentnodefactory) {}

	/**
	 * Add a node to the flow.
	 */
	public function addNode(IAgentNode $node): void {
		$this->nodes[$node->getId()] = $node;
	}

	/**
	 * Define a connection between two nodes.
	 */
	public function addConnection(string $fromNode, string $fromOutput, string $toNode, string $toInput): void {
		$this->connections[] = [
			'fromNode' => $fromNode,
			'fromOutput' => $fromOutput,
			'toNode' => $toNode,
			'toInput' => $toInput
		];
	}

	/**
	 * Load a flow from array.
	 */
	public function fromArray(array $data): self {
		foreach ($data['nodes'] ?? [] as $nodeData) {
			$type = $nodeData['type'] ?? null;
			$id = $nodeData['id'] ?? null;

			if (!$type || !$id) continue;

			$node = $this->agentnodefactory->createNode($type);
			if (!$node instanceof IAgentNode) continue;

			$node->setId($id);
			$this->addNode($node);

			if (!empty($nodeData['inputs'])) {
				$this->initialInputs[$id] = $nodeData['inputs'];
			}
		}

		foreach ($data['connections'] ?? [] as $conn) {
			$this->addConnection(
				$conn['from'] ?? '',
				$conn['output'] ?? '',
				$conn['to'] ?? '',
				$conn['input'] ?? ''
			);
		}

		return $this;
	}

	/**
	 * Execute the flow.
	 */
	public function run(array $inputs, IAgentContext $context): array {
		$nodeInputs = [];
		$nodeOutputs = [];

		foreach ($this->nodes as $nodeId => $_) {
			$nodeInputs[$nodeId] = [];
		}

		foreach ($this->initialInputs as $nodeId => $preset) {
			foreach ($preset as $key => $value) {
				$nodeInputs[$nodeId][$key] = $value;
			}
		}

		foreach ($inputs as $inputName => $value) {
			foreach ($this->connections as $conn) {
				if ($conn['fromNode'] === '__input__' && $conn['fromOutput'] === $inputName) {
					$nodeInputs[$conn['toNode']][$conn['toInput']] = $value;
				}
			}
		}

		$executed = [];
		$loopGuard = 0;
		$maxLoops = 1000;

		while (count($executed) < count($this->nodes)) {
			if (++$loopGuard > $maxLoops) {
				return [['error' => 'Flow execution exceeded safe iteration limit']];
			}

			$progress = false;

			foreach ($this->nodes as $nodeId => $node) {
				if (in_array($nodeId, $executed)) continue;
				if (!isset($nodeInputs[$nodeId])) continue;

				$inputDefs = $this->normalizePortDefs($node->getInputDefinitions());
				foreach ($inputDefs as $port) {
					if (!array_key_exists($port->name, $nodeInputs[$nodeId])) {
						if ($port->required && $port->default === null) {
							$nodeOutputs[$nodeId] = ['error' => "Missing required input '{$port->name}' for node '$nodeId'"];
							$executed[] = $nodeId;
							$progress = true;
							continue 2;  // next round outer foreach
						}
						$nodeInputs[$nodeId][$port->name] = $port->default;
					}
				}

				try {
					$output = $node->execute($nodeInputs[$nodeId], $context);
				} catch (\Throwable $e) {
					$output = ['error' => $e->getMessage()];
				}

				$outputDefs = $this->normalizePortDefs($node->getOutputDefinitions());
				foreach ($outputDefs as $port) {
					if (!array_key_exists($port->name, $output)) {
						if ($port->default !== null) {
							$output[$port->name] = $port->default;
						}
					}
				}

				$nodeOutputs[$nodeId] = $output;
				$executed[] = $nodeId;
				$progress = true;

				foreach ($this->connections as $conn) {
					if ($conn['fromNode'] === $nodeId && isset($output[$conn['fromOutput']])) {
						$nodeInputs[$conn['toNode']][$conn['toInput']] = $output[$conn['fromOutput']];
					}
				}
			}

			if (!$progress) {
				return [['error' => 'Flow execution stalled due to incomplete input graph']];
			}
		}

		$terminalNodes = [];
		foreach ($this->nodes as $nodeId => $_) {
			$hasOutgoing = false;
			foreach ($this->connections as $conn) {
				if ($conn['fromNode'] === $nodeId) {
					$hasOutgoing = true;
					break;
				}
			}
			if (!$hasOutgoing) $terminalNodes[] = $nodeId;
		}

		$outputs = [];
		foreach ($terminalNodes as $nodeId) {
			if (isset($nodeOutputs[$nodeId])) {
				$outputs[] = $nodeOutputs[$nodeId];
			}
		}

		return $outputs;
	}

	/**
	 * Normalizes input/output definitions to AgentNodePort[].
	 *
	 * @param array<string|AgentNodePort> $defs
	 * @return AgentNodePort[]
	 */
	private function normalizePortDefs(array $defs): array {
		$ports = [];

		foreach ($defs as $def) {
			if ($def instanceof AgentNodePort) {
				$ports[] = $def;
			} elseif (is_string($def)) {
				$ports[] = new AgentNodePort(name: $def);
			}
		}

		return $ports;
	}
}

