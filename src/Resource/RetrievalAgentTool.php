<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentTool;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentVectorStore;
use MissionBay\Resource\AbstractAgentResource;
use AssistantFoundation\Api\IAiEmbeddingModel;
use Base3\Logger\Api\ILogger;

/**
 * RetrievalAgentTool
 *
 * Retrieves relevant documents from a vector store (IAgentVectorStore)
 * given a query string, using embeddings (IAiEmbeddingModel).
 * Uses a configured collectionKey for multi-collection vector stores.
 * Logs every step via ILogger to simplify debugging.
 */
class RetrievalAgentTool extends AbstractAgentResource implements IAgentTool {

        protected IAgentConfigValueResolver $resolver;

        protected int $limit = 3;
        protected ?float $minScore = 0.75;

        /**
         * Collection routing is decided upstream and carried here as config.
         * This tool will always search in exactly this configured collectionKey.
         */
        protected string $collectionKey = 'default';

        /**
         * Optional: create collection on init (helpful for local/dev setups)
         */
        protected bool $createCollectionOnInit = false;

        protected ?IAiEmbeddingModel $embeddingModel = null;
        protected ?IAgentVectorStore $vectorStore = null;
        protected ?ILogger $logger = null;

        public function __construct(IAgentConfigValueResolver $resolver, ?string $id = null) {
                parent::__construct($id);
                $this->resolver = $resolver;
        }

        public static function getName(): string {
                return 'retrievalagenttool';
        }

        public function getDescription(): string {
                return 'Searches a vector store for relevant documents in a configured collectionKey.';
        }

        public function setConfig(array $config): void {
                parent::setConfig($config);

                if (isset($config['limit'])) {
                        $this->limit = (int)$this->resolver->resolveValue($config['limit']);
                }
                if (isset($config['minscore'])) {
                        $this->minScore = (float)$this->resolver->resolveValue($config['minscore']);
                }

                // NEW: collection key
                if (isset($config['collectionkey'])) {
                        $key = (string)$this->resolver->resolveValue($config['collectionkey']);
                        $key = trim($key);
                        if ($key !== '') {
                                $this->collectionKey = $key;
                        }
                }

                // Optional helper
                if (isset($config['createcollection'])) {
                        $this->createCollectionOnInit = (bool)$this->resolver->resolveValue($config['createcollection']);
                }
        }

        public function init(array $resources, IAgentContext $context): void {
                if (isset($resources['embedding'][0]) && $resources['embedding'][0] instanceof IAiEmbeddingModel) {
                        $this->embeddingModel = $resources['embedding'][0];
                }
                if (isset($resources['vectorstore'][0]) && $resources['vectorstore'][0] instanceof IAgentVectorStore) {
                        $this->vectorStore = $resources['vectorstore'][0];
                }
                if (isset($resources['logger'][0]) && $resources['logger'][0] instanceof ILogger) {
                        $this->logger = $resources['logger'][0];
                }

                $this->log("Initialized with collectionKey={$this->collectionKey}, limit={$this->limit}, minScore={$this->minScore}");

                if ($this->createCollectionOnInit && $this->vectorStore) {
                        try {
                                $this->vectorStore->createCollection($this->collectionKey);
                                $this->log("Ensured collection exists: {$this->collectionKey}");
                        } catch (\Throwable $e) {
                                $this->log("WARN: createCollection failed for {$this->collectionKey}: " . $e->getMessage());
                        }
                }
        }

        public function getToolDefinitions(): array {
                return [[
                        'type' => 'function',
                        'label' => 'Knowledge Base Lookup',
                        'category' => 'knowledge',
                        'tags' => ['retrieval', 'search', 'docs', 'schema'],
                        'priority' => 50,
                        'function' => [
                                'name' => 'retrieval_search',
                                'description' => 'Searches the vector store for documents relevant to a query, within the configured collectionKey.',
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
                if ($toolName !== 'retrieval_search') {
                        throw new \InvalidArgumentException("Unsupported tool: $toolName");
                }
                if (!$this->embeddingModel) {
                        return $this->error("Missing embedding model resource");
                }
                if (!$this->vectorStore) {
                        return $this->error("Missing vector store resource");
                }

                $query = $arguments['query'] ?? null;
                if (!is_string($query) || trim($query) === '') {
                        return $this->error("Missing required parameter: query");
                }
                $query = trim($query);

                $this->log("Starting retrieval search (collectionKey={$this->collectionKey}) for query: " . $query);

                // Step 1: create embedding
                try {
                        $embeddings = $this->embeddingModel->embed([$query]);
                        $vector = $embeddings[0] ?? null;
                } catch (\Throwable $e) {
                        return $this->error("Embedding generation failed: " . $e->getMessage());
                }

                if (!is_array($vector) || count($vector) === 0) {
                        return $this->error("No embedding generated for query");
                }
                $this->log("Generated embedding of dimension " . count($vector));

                // Step 2: search in VectorStore (collectionKey is mandatory here)
                try {
                        $results = $this->vectorStore->search($this->collectionKey, $vector, $this->limit, $this->minScore);
                } catch (\Throwable $e) {
                        return $this->error("Vector store search failed: " . $e->getMessage());
                }

                $this->log("Vector store search returned " . count($results) . " results");

                foreach ($results as $i => $hit) {
                        $this->log("Result #$i: id=" . ($hit['id'] ?? 'n/a') . " score=" . ($hit['score'] ?? 'n/a'));
                }

                return [
                        'query' => $query,
                        'collectionKey' => $this->collectionKey,
                        'results' => $results
                ];
        }

        // Convenience: log with consistent prefix
        protected function log(string $message): void {
                if (!$this->logger) return;
                $fullMsg = '[' . $this->getName() . '|' . $this->getId() . '] ' . $message;
                $this->logger->log('RetrievalAgentTool', $fullMsg);
        }

        // Convenience: error response with logging
        protected function error(string $message): array {
                $this->log("ERROR: " . $message);
                return ['error' => $message];
        }
}
