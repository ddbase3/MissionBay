<?php declare(strict_types=1);

namespace MissionBay\Agent;

use MissionBay\Api\IAgentNode;
use Base3\Api\IClassMap;

class AgentFlow {

	private array $nodes = [];
	private array $connections = [];
	private array $initialInputs = [];

	/**
	 * Add a node to the flow.
	 */
	public function addNode(IAgentNode $node): void {
		$this->nodes[$node->getId()] = $node;
	}

	/**
	 * Define a connection between two nodes.
	 *
	 * @param string $fromNode
	 * @param string $fromOutput
	 * @param string $toNode
	 * @param string $toInput
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
	public static function fromArray(array $data, IClassMap $classMap): self {
		$flow = new self();

		foreach ($data['nodes'] ?? [] as $nodeData) {
			$type = $nodeData['type'] ?? null;
			$id = $nodeData['id'] ?? null;

			if (!$type || !$id) continue;

			$node = $classMap->getInstanceByInterfaceName(IAgentNode::class, $type);
			if (!$node instanceof IAgentNode) continue;

			$node->setId($id);
			$flow->addNode($node);

			if (!empty($nodeData['inputs'])) {
				$flow->initialInputs[$id] = $nodeData['inputs'];
			}
		}

		foreach ($data['connections'] ?? [] as $conn) {
			$flow->addConnection(
				$conn['from'] ?? '',
				$conn['output'] ?? '',
				$conn['to'] ?? '',
				$conn['input'] ?? ''
			);
		}

		return $flow;
	}

	/**
	 * Execute the flow.
	 */
	public function run(array $inputs, AgentContext $context): array {
		$nodeInputs = [];
		$nodeOutputs = [];

		foreach ($this->nodes as $nodeId => $_) {
			$nodeInputs[$nodeId] = [];
		}

		// Inputs aus initialInputs (aus fromArray)
		foreach ($this->initialInputs as $nodeId => $preset) {
			foreach ($preset as $key => $value) {
				$nodeInputs[$nodeId][$key] = $value;
			}
		}

		// Inputs aus run() Parameter (z.B. für __input__)
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

				try {
					$output = $node->execute($nodeInputs[$nodeId], $context);
				} catch (\Throwable $e) {
					$output = ['error' => $e->getMessage()];
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
}

