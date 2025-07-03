<?php declare(strict_types=1);

namespace MissionBay\Flow;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentNode;
use MissionBay\Agent\AgentNodePort;

class StrictFlow extends AbstractFlow {

    private array $connections = [];
    private array $initialInputs = [];

    public static function getName(): string {
        return 'strictflow';
    }

    public function addConnection(string $fromNode, string $fromOutput, string $toNode, string $toInput): void {
        $this->connections[] = [
            'fromNode' => $fromNode,
            'fromOutput' => $fromOutput,
            'toNode' => $toNode,
            'toInput' => $toInput
        ];
    }

    public function addInitialInput(string $nodeId, string $key, mixed $value): void {
        $this->initialInputs[$nodeId][$key] = $value;
    }

    public function getConnections(): array {
        return $this->connections;
    }

    public function getInitialInputs(): array {
        return $this->initialInputs;
    }

    public function getNextNode(string $currentNodeId, array $output): ?string {
        foreach ($this->connections as $conn) {
            if ($conn['fromNode'] === $currentNodeId) {
                return $conn['toNode'];
            }
        }
        return null;
    }

    public function mapInputs(string $fromNodeId, string $toNodeId, array $output): array {
        $mapped = [];
        foreach ($this->connections as $conn) {
            if ($conn['fromNode'] === $fromNodeId && $conn['toNode'] === $toNodeId) {
                $mapped[$conn['toInput']] = $output[$conn['fromOutput']] ?? null;
            }
        }
        return $mapped;
    }

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
                foreach ($nodeData['inputs'] as $key => $value) {
                    $this->addInitialInput($id, $key, $value);
                }
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

    public function run(array $inputs): array {
        if (!$this->context) throw new \RuntimeException("Context is not set");

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
                if (!$this->allowReentrant && in_array($nodeId, $executed)) continue;
                if (!$this->isReady($nodeId, $nodeInputs[$nodeId] ?? [])) continue;

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
                    if (!array_key_exists($port->name, $output) && $port->default !== null) {
                        $output[$port->name] = $port->default;
                    }
                }

                $nodeOutputs[$nodeId] = $output;
                $executed[] = $nodeId;
                $progress = true;

                foreach ($this->connections as $conn) {
                    if ($conn['fromNode'] === $nodeId) {
                        $toNode = $conn['toNode'];
                        $fromOutput = $conn['fromOutput'];
                        $toInput = $conn['toInput'];
                        if (isset($this->nodes[$toNode]) && array_key_exists($fromOutput, $output)) {
                            $nodeInputs[$toNode][$toInput] = $output[$fromOutput] ?? null;
                        }
                    }
                }
            }

            if (!$progress) break;
        }

        $terminalNodes = array_filter(array_keys($this->nodes), function ($nodeId) {
            foreach ($this->connections as $conn) {
                if ($conn['fromNode'] === $nodeId) return false;
            }
            return true;
        });

        $outputs = [];
        foreach ($terminalNodes as $nodeId) {
            if (isset($nodeOutputs[$nodeId])) {
                $outputs[$nodeId] = $nodeOutputs[$nodeId];
            }
        }

        return $outputs;
    }

    public function isReady(string $nodeId, array $currentInputs): bool {
        foreach ($this->connections as $conn) {
            if ($conn['toNode'] === $nodeId && !array_key_exists($conn['toInput'], $currentInputs)) {
                return false;
            }
        }
        return true;
    }
}

