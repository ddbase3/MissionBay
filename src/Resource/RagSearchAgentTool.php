<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentTool;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Resource\AbstractAgentResource;
use AssistantFoundation\Api\IAiEmbeddingModel;
use AssistantFoundation\Api\IVectorSearch;
use Base3\Logger\Api\ILogger;

/**
 * RagSearchAgentTool
 *
 * Retrieves relevant documents from a vector store (IVectorSearch)
 * given a query string, using embeddings (IAiEmbeddingModel).
 * Logs every step via ILogger to simplify debugging.
 */
class RagSearchAgentTool extends AbstractAgentResource implements IAgentTool {

	protected IAgentConfigValueResolver $resolver;

	protected int $limit = 3;
	protected ?float $minScore = 0.75;

	protected ?IAiEmbeddingModel $embeddingModel = null;
	protected ?IVectorSearch $vectorSearch = null;
	protected ?ILogger $logger = null;

	public function __construct(IAgentConfigValueResolver $resolver, ?string $id = null) {
		parent::__construct($id);
		$this->resolver = $resolver;
	}

	public static function getName(): string {
		return 'ragsearchagenttool';
	}

	public function getDescription(): string {
		return 'Searches a vector store for relevant documents based on a query string.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		if (isset($config['limit'])) {
			$this->limit = (int)$this->resolver->resolveValue($config['limit']);
		}
		if (isset($config['minscore'])) {
			$this->minScore = (float)$this->resolver->resolveValue($config['minscore']);
		}
	}

	public function init(array $resources, IAgentContext $context): void {
		if (isset($resources['embedding'][0]) && $resources['embedding'][0] instanceof IAiEmbeddingModel) {
			$this->embeddingModel = $resources['embedding'][0];
		}
		if (isset($resources['vectordb'][0]) && $resources['vectordb'][0] instanceof IVectorSearch) {
			$this->vectorSearch = $resources['vectordb'][0];
		}
		if (isset($resources['logger'][0]) && $resources['logger'][0] instanceof ILogger) {
			$this->logger = $resources['logger'][0];
		}

		$this->log("Initialized with limit={$this->limit}, minScore={$this->minScore}");
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'function' => [
				'name' => 'rag_search',
				'description' => 'Searches the vector database for documents relevant to a query.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'query' => [
							'type' => 'string',
							'description' => 'The natural language query to search for.'
						]
					],
					'required' => ['query']
				]
			]
		]];
	}

	public function callTool(string $toolName, array $arguments, IAgentContext $context): array {
		if ($toolName !== 'rag_search') {
			throw new \InvalidArgumentException("Unsupported tool: $toolName");
		}
		if (!$this->embeddingModel) {
			return $this->error("Missing embedding model resource");
		}
		if (!$this->vectorSearch) {
			return $this->error("Missing vector search resource");
		}

		$query = $arguments['query'] ?? null;
		if (!$query) {
			return $this->error("Missing required parameter: query");
		}

		$this->log("Starting RAG search for query: " . $query);

		// Step 1: create embedding
		try {
			$embeddings = $this->embeddingModel->embed([$query]);
			$vector = $embeddings[0] ?? null;
		} catch (\Throwable $e) {
			return $this->error("Embedding generation failed: " . $e->getMessage());
		}

		if (!$vector) {
			return $this->error("No embedding generated for query");
		}
		$this->log("Generated embedding of dimension " . count($vector));

		// Step 2: search in vector DB
		try {
			$results = $this->vectorSearch->search($vector, $this->limit, $this->minScore);
		} catch (\Throwable $e) {
			return $this->error("Vector search failed: " . $e->getMessage());
		}

		$this->log("Vector search returned " . count($results) . " results");

		foreach ($results as $i => $hit) {
			$this->log("Result #$i: id=" . ($hit['id'] ?? 'n/a') . " score=" . ($hit['score'] ?? 'n/a'));
		}

		return [
			'query' => $query,
			'results' => $results
		];
	}

	// Convenience: log with consistent prefix
	protected function log(string $message): void {
		if (!$this->logger) return;
		$fullMsg = '[' . $this->getName() . '|' . $this->getId() . '] ' . $message;
		$this->logger->log('RagSearchAgentTool', $fullMsg);
	}

	// Convenience: error response with logging
	protected function error(string $message): array {
		$this->log("ERROR: " . $message);
		return ['error' => $message];
	}
}

