<?php declare(strict_types=1);

namespace MissionBay\Flow;

use MissionBay\Api\IAgentFlow;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentNode;
use MissionBay\Api\IAgentNodeFactory;
use Base3\Api\IClassMap;
use Base3\Configuration\Api\IConfiguration;

class DynamicAiFlow implements IAgentFlow {

    private array $nodes = [];
    private ?IAgentContext $context = null;
    private string $model = 'gpt-3.5-turbo'; // gpt-4o  // You can change this to 'gpt-3.5-turbo' or any other model

    public function __construct(
	    private readonly IAgentNodeFactory $agentnodefactory,
	    private readonly IClassMap $classmap,
	    private readonly IConfiguration $configuration
    ) {}

    public static function getName(): string {
        return 'dynamicaiflow';
    }

    public function setContext(IAgentContext $context): void {
        $this->context = $context;
    }

    public function addNode(IAgentNode $node): void {
        $this->nodes[$node->getId()] = $node;
    }

    public function getNodes(): array {
        return $this->nodes;
    }

    public function getConnections(): array {
        // Dynamic flow does not use predefined connections
        return [];
    }

    public function getInitialInputs(): array {
        // Dynamic flow does not use predefined initial inputs
        return [];
    }

    public function addConnection(string $fromNode, string $fromOutput, string $toNode, string $toInput): void {
        // Not used in dynamic flow
    }

    public function addInitialInput(string $nodeId, string $key, mixed $value): void {
        // Not used in dynamic flow
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
        }

        return $this;
    }

    public function run(array $inputs): array {
        if (!$this->context) {
            throw new \RuntimeException("Context is not set");
        }

        $executed = [];
        $nodeInputs = [];

        // Initialize inputs for each node
        foreach ($this->nodes as $nodeId => $_) {
            $nodeInputs[$nodeId] = [];
        }

        // Set initial inputs
        foreach ($inputs as $key => $value) {
            $nodeInputs['__input__'][$key] = $value;
        }

        $currentNodeId = $this->determineNextNode(null, []);

        while ($currentNodeId !== null) {
            if (in_array($currentNodeId, $executed)) {
                break; // Prevent infinite loops
            }

            $node = $this->nodes[$currentNodeId] ?? null;
            if (!$node) {
                break;
            }

            $inputData = $nodeInputs[$currentNodeId] ?? [];

            try {
                $output = $node->execute($inputData, $this->context);
            } catch (\Throwable $e) {
                $output = ['error' => $e->getMessage()];
            }

            $executed[] = $currentNodeId;

            // Determine next node and input mapping using ChatGPT
            $nextNodeId = $this->determineNextNode($currentNodeId, $output);
            if ($nextNodeId !== null) {
                $mappedInputs = $this->mapInputs($currentNodeId, $nextNodeId, $output);
                foreach ($mappedInputs as $key => $value) {
                    $nodeInputs[$nextNodeId][$key] = $value;
                }
            }

            $currentNodeId = $nextNodeId;
        }

        // Collect outputs from executed nodes
        $outputs = [];
        foreach ($executed as $nodeId) {
            $outputs[$nodeId] = $nodeInputs[$nodeId] ?? [];
        }

        return $outputs;
    }

    public function isReady(string $nodeId, array $currentInputs): bool {
        // In dynamic flow, readiness is determined at runtime
        return true;
    }

    public function getNextNode(string $currentNodeId, array $output): ?string {
        return $this->determineNextNode($currentNodeId, $output);
    }

    public function mapInputs(string $fromNodeId, string $toNodeId, array $output): array {
        // Prepare prompt for ChatGPT to determine input mapping
        $prompt = $this->buildPromptForMapping($fromNodeId, $toNodeId, $output);
        $response = $this->callOpenAiApi($prompt);

        // Parse response to extract input mapping
        $mapping = json_decode($response, true);
        return $mapping ?? [];
    }

    private function determineNextNode(?string $currentNodeId, array $output): ?string {
        // Prepare prompt for ChatGPT to determine next node
        $prompt = $this->buildPromptForNextNode($currentNodeId, $output);
        $response = $this->callOpenAiApi($prompt);

        // Parse response to extract next node ID
        $nextNodeId = trim($response, "\" \n\r\t");
        return $nextNodeId !== '' ? $nextNodeId : null;
    }

    private function buildPromptForNextNode(?string $currentNodeId, array $output): string {
        $nodeDescriptions = $this->getNodeDescriptions();
        $currentOutput = json_encode($output, JSON_PRETTY_PRINT);

        return <<<EOT
You are managing a dynamic flow of nodes. Each node performs a specific task. Based on the current node's output, determine the ID of the next node to execute.

Available nodes:
{$nodeDescriptions}

Current node ID: {$currentNodeId}
Current output:
{$currentOutput}

Please provide the ID of the next node to execute.
EOT;
    }

    private function buildPromptForMapping(string $fromNodeId, string $toNodeId, array $output): string {
        $fromOutput = json_encode($output, JSON_PRETTY_PRINT);
        $toNode = $this->nodes[$toNodeId];
        $inputDefs = $toNode->getInputDefinitions();
        $inputDefsJson = json_encode($inputDefs, JSON_PRETTY_PRINT);

        return <<<EOT
You are managing a dynamic flow of nodes. Each node has specific input definitions.

From node ID: {$fromNodeId}
To node ID: {$toNodeId}

From node output:
{$fromOutput}

To node input definitions:
{$inputDefsJson}

Based on the output of the from node, provide a JSON object mapping input keys to values for the to node.
EOT;
    }

    private function getNodeDescriptions(): string {
        $descriptions = [];
        foreach ($this->nodes as $nodeId => $node) {
            $type = get_class($node);
            $description = $node->getDescription();
            $descriptions[] = "ID: {$nodeId}, Type: {$type}, Description: {$description}";
        }
        return implode("\n", $descriptions);
    }

    private function callOpenAiApi(string $prompt): string {
        $cnf = $this->configuration->get('openaiconversation');
        $apikey = $cnf['apikey'];

        $url = 'https://api.openai.com/v1/chat/completions';

        $data = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are an AI assistant helping to manage a dynamic flow of nodes.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.7,
            'max_tokens' => 150,
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apikey,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new \RuntimeException('Curl error: ' . curl_error($ch));
        }
        curl_close($ch);

        $responseData = json_decode($response, true);
        return $responseData['choices'][0]['message']['content'] ?? '';
    }
}

