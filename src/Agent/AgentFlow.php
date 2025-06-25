<?php declare(strict_types=1);

namespace MissionBay\Agent;

use MissionBay\Api\IAgentFlow;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentNode;
use MissionBay\Api\IAgentNodeFactory;

class AgentFlow implements IAgentFlow {

	private array $nodes = [];
	private ?IAgentContext $context = null;

	public function __construct(private readonly IAgentNodeFactory $agentnodefactory) {}

	public function setContext(IAgentContext $context): void {
		$this->context = $context;
	}

	public function addNode(IAgentNode $node): void {
		$this->nodes[$node->getId()] = $node;
	}

	public function addConnection(string $fromNode, string $fromOutput, string $toNode, string $toInput): void {
		if (!$this->context) {
			throw new \RuntimeException("AgentContext must be set before adding connections.");
		}
		$this->context->getRouter()->addConnection($fromNode, $fromOutput, $toNode, $toInput);
	}

	public function fromArray(array $data): self {
		if (!$this->context) {
			throw new \RuntimeException("AgentContext must be set before loading flow from array.");
		}

		$router = $this->context->getRouter();

		foreach ($data['nodes'] ?? [] as $nodeData) {
			$type = $nodeData['type'] ?? null;
			$id = $nodeData['id'] ?? null;

			if (!$type || !$id) continue;

			$node = $this->agentnodefactory->createNode($type);
			if (!$node instanceof IAgentNode) continue;

			$node->setId($id);
			$this->addNode($node);

			if (!empty($nodeData['inputs'])) {
				foreach ($nodeData['inputs'] as $key => $value) {
					$router->addInitialInput($id, $key, $value);
				}
			}
		}

		foreach ($data['connections'] ?? [] as $conn) {
			$router->addConnection(
				$conn['from'] ?? '',
				$conn['output'] ?? '',
				$conn['to'] ?? '',
				$conn['input'] ?? '',
				$conn['mandatory'] ?? false
			);
		}

		return $this;
	}

	public function run(array $inputs): array {
		if (!$this->context) {
			throw new \RuntimeException("AgentContext must be set before running the flow.");
		}

		$router = $this->context->getRouter();
		$nodeInputs = [];
		$nodeOutputs = [];

		foreach ($this->nodes as $nodeId => $_) {
			$nodeInputs[$nodeId] = [];
		}

		foreach ($router->getInitialInputs() as $nodeId => $preset) {
			foreach ($preset as $key => $value) {
				$nodeInputs[$nodeId][$key] = $value;
			}
		}

		foreach ($inputs as $inputName => $value) {
			foreach ($router->getConnections() as $conn) {
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

				if (!$router->isReady($nodeId, $nodeInputs[$nodeId], $this->context)) continue;

				$inputDefs = $this->normalizePortDefs($node->getInputDefinitions());
				foreach ($inputDefs as $port) {
					if (!array_key_exists($port->name, $nodeInputs[$nodeId])) {
						if ($port->required && $port->default === null) {
							$nodeOutputs[$nodeId] = ['error' => "Missing required input '{$port->name}' for node '$nodeId'"];
							$executed[] = $nodeId;
							$progress = true;
							continue 2;
						}
						$nodeInputs[$nodeId][$port->name] = $port->default;
					}
				}

				try {
					$output = $node->execute($nodeInputs[$nodeId], $this->context);
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

				foreach ($router->getConnections() as $conn) {
					if ($conn['fromNode'] === $nodeId) {
						$toNode = $conn['toNode'];
						if (!isset($this->nodes[$toNode])) continue;
						$mapped = $router->mapInputs($nodeId, $toNode, $output, $this->context);
						foreach ($mapped as $k => $v) {
							$nodeInputs[$toNode][$k] = $v;
						}
					}
				}
			}

			if (!$progress) {
				// may happen with dynamic flows
				// return [['error' => 'Flow execution stalled due to incomplete input graph']];

				$remaining = array_diff(array_keys($this->nodes), $executed);
				if (!empty($remaining)) {
					// optional: Logging der ungenutzten Nodes
					break;
				}
			}
		}

		$terminalNodes = [];
		foreach ($this->nodes as $nodeId => $_) {
			$hasOutgoing = false;
			foreach ($router->getConnections() as $conn) {
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

	private function normalizePortDefs(array $defs): array {
		$ports = [];
		foreach ($defs as $def) {
			if ($def instanceof \MissionBay\Agent\AgentNodePort) {
				$ports[] = $def;
			} elseif (is_string($def)) {
				$ports[] = new \MissionBay\Agent\AgentNodePort(name: $def);
			}
		}
		return $ports;
	}
}

