<?php declare(strict_types=1);

namespace MissionBay\Flow;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentNode;
use MissionBay\Api\IAgentNodeFactory;
use MissionBay\Api\IAgentResource;
use Base3\Api\IClassMap;
use Base3\Configuration\Api\IConfiguration;

class DynamicAiFlow extends AbstractFlow {

	private array $dockConnections = [];
	private string $model = 'gpt-3.5-turbo';

	public function __construct(
		protected readonly IAgentNodeFactory $agentnodefactory,
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

	public function addResource(IAgentResource $resource): void {
		$this->resources[$resource->getId()] = $resource;
	}

	public function getResources(): array {
		return $this->resources;
	}

	public function addDockConnection(string $nodeId, string $dockName, string $resourceId): void {
		$this->dockConnections[$nodeId][$dockName][] = $resourceId;
	}

	public function getDockConnections(string $nodeId): array {
		return $this->dockConnections[$nodeId] ?? [];
	}

	public function getAllDockConnections(): array {
		return $this->dockConnections;
	}

	public function getConnections(): array {
		return [];
	}

	public function getInitialInputs(): array {
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

			if (!empty($nodeData['docks'])) {
				foreach ($nodeData['docks'] as $dockName => $resourceIds) {
					foreach ((array)$resourceIds as $resourceId) {
						$this->addDockConnection($id, $dockName, $resourceId);
					}
				}
			}
		}

		return $this;
	}

	public function run(array $inputs): array {
		if (!$this->context) {
			throw new \RuntimeException("Context is not set");
		}

		// Ressourcen bereitstellen
		$resolved = [];
		foreach ($this->nodes as $nodeId => $_) {
			$resolved[$nodeId] = [];
			foreach ($this->getDockConnections($nodeId) as $dockName => $resourceIds) {
				foreach ((array)$resourceIds as $rid) {
					if (isset($this->resources[$rid])) {
						$resolved[$nodeId][$dockName][] = $this->resources[$rid];
					}
				}
			}
		}
		$this->context->setDockedResources($resolved);

		$executed = [];
		$nodeInputs = [];

		foreach ($this->nodes as $nodeId => $_) {
			$nodeInputs[$nodeId] = [];
		}

		foreach ($inputs as $key => $value) {
			$nodeInputs['__input__'][$key] = $value;
		}

		$currentNodeId = $this->determineNextNode(null, []);

		while ($currentNodeId !== null) {
			if (in_array($currentNodeId, $executed)) {
				break;
			}

			$node = $this->nodes[$currentNodeId] ?? null;
			if (!$node) {
				break;
			}

			$inputData = $nodeInputs[$currentNodeId] ?? [];

			try {
				$output = $node->execute($inputData, $this->context, $this);
			} catch (\Throwable $e) {
				$output = ['error' => $e->getMessage()];
			}

			$executed[] = $currentNodeId;

			$nextNodeId = $this->determineNextNode($currentNodeId, $output);
			if ($nextNodeId !== null) {
				$mappedInputs = $this->mapInputs($currentNodeId, $nextNodeId, $output);
				foreach ($mappedInputs as $key => $value) {
					$nodeInputs[$nextNodeId][$key] = $value;
				}
			}

			$currentNodeId = $nextNodeId;
		}

		$outputs = [];
		foreach ($executed as $nodeId) {
			$outputs[$nodeId] = $nodeInputs[$nodeId] ?? [];
		}

		return $outputs;
	}

	public function isReady(string $nodeId, array $currentInputs): bool {
		return true;
	}

	public function getNextNode(string $currentNodeId, array $output): ?string {
		return $this->determineNextNode($currentNodeId, $output);
	}

	public function mapInputs(string $fromNodeId, string $toNodeId, array $output): array {
		$prompt = $this->buildPromptForMapping($fromNodeId, $toNodeId, $output);
		$response = $this->callOpenAiApi($prompt);
		$mapping = json_decode($response, true);
		return $mapping ?? [];
	}

	private function determineNextNode(?string $currentNodeId, array $output): ?string {
		$prompt = $this->buildPromptForNextNode($currentNodeId, $output);
		$response = $this->callOpenAiApi($prompt);
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
		$cnf = $this->configuration->get('openai');
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

