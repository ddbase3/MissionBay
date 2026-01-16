<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentTool;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentVectorFilter;
use MissionBay\Api\IAgentVectorStore;
use AssistantFoundation\Api\IAiEmbeddingModel;
use Base3\Logger\Api\ILogger;

/**
 * RetrievalAgentTool
 *
 * Read-only vector retrieval tool.
 * No collection lifecycle side effects.
 */
class RetrievalAgentTool extends AbstractAgentResource implements IAgentTool {

	protected IAgentConfigValueResolver $resolver;

	protected int $limit = 3;
	protected ?float $minScore = 0.75;

	/**
	 * Collection routing is decided upstream and carried here as config.
	 */
	protected string $collectionKey = 'default';

	protected ?IAiEmbeddingModel $embeddingModel = null;
	protected ?IAgentVectorStore $vectorStore = null;
	protected ?ILogger $logger = null;

	/** @var IAgentVectorFilter[] */
	protected array $filters = [];

	public function __construct(IAgentConfigValueResolver $resolver, ?string $id = null) {
		parent::__construct($id);
		$this->resolver = $resolver;
	}

	public static function getName(): string {
		return 'retrievalagenttool';
	}

	public function getDescription(): string {
		return 'Performs read-only similarity search on a vector store.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		if (isset($config['limit'])) {
			$this->limit = (int)$this->resolver->resolveValue($config['limit']);
		}
		if (isset($config['minscore'])) {
			$this->minScore = (float)$this->resolver->resolveValue($config['minscore']);
		}

		if (isset($config['collectionkey'])) {
			$key = (string)$this->resolver->resolveValue($config['collectionkey']);
			$key = trim($key);
			if ($key !== '') {
				$this->collectionKey = $key;
			}
		}
	}

	public function init(array $resources, IAgentContext $context): void {
		$this->embeddingModel = $this->pickResource($resources, 'embedding', IAiEmbeddingModel::class);
		$this->vectorStore = $this->pickResource($resources, 'vectorstore', IAgentVectorStore::class);
		$this->logger = $this->pickResource($resources, 'logger', ILogger::class);
		$this->filters = $this->pickResources($resources, 'filters', IAgentVectorFilter::class);

		$this->log(
			"Initialized collectionKey={$this->collectionKey}, limit={$this->limit}, minScore={$this->minScore}, filters=" . count($this->filters)
		);
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'Knowledge Base Lookup',
			'category' => 'knowledge',
			'tags' => ['retrieval', 'search'],
			'priority' => 50,
			'function' => [
				'name' => 'retrieval_search',
				'description' => 'Searches the vector store for documents relevant to a query.',
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
		if (!$this->embeddingModel || !$this->vectorStore) {
			return $this->error('Retrieval tool not fully initialized.');
		}

		$query = $arguments['query'] ?? null;
		if (!is_string($query) || trim($query) === '') {
			return $this->error('Missing required parameter: query');
		}
		$query = trim($query);

		$filterSpec = $this->buildMergedFilterSpec();

		$this->log(
			"Search collectionKey={$this->collectionKey} query=\"{$query}\" filter=" .
			($filterSpec ? json_encode($filterSpec) : 'null')
		);

		try {
			$vector = $this->embeddingModel->embed([$query])[0] ?? null;
		} catch (\Throwable $e) {
			return $this->error('Embedding generation failed: ' . $e->getMessage());
		}

		if (!is_array($vector) || $vector === []) {
			return $this->error('No embedding generated for query.');
		}

		try {
			$results = $this->vectorStore->search(
				$this->collectionKey,
				$vector,
				$this->limit,
				$this->minScore,
				$filterSpec
			);
		} catch (\Throwable $e) {
			return $this->error('Vector store search failed: ' . $e->getMessage());
		}

		return [
			'query' => $query,
			'collectionKey' => $this->collectionKey,
			'filter' => $filterSpec,
			'results' => $results
		];
	}

	// -------------------------------------------------
	// Filter merge
	// -------------------------------------------------

	protected function buildMergedFilterSpec(): ?array {
		$out = null;

		foreach ($this->filters as $filter) {
			$spec = $filter->getFilterSpec();
			if (is_array($spec)) {
				$out = $this->mergeFilterSpecs($out, $spec);
			}
		}

		return $out;
	}

	protected function mergeFilterSpecs(?array $a, array $b): array {
		$out = $a ?? [];

		$out['must'] = $this->mergeFilterGroup($out['must'] ?? null, $b['must'] ?? null);
		$out['any'] = $this->mergeFilterGroup($out['any'] ?? null, $b['any'] ?? null);
		$out['must_not'] = $this->mergeFilterGroup($out['must_not'] ?? null, $b['must_not'] ?? null);

		if (empty($out['must'])) unset($out['must']);
		if (empty($out['any'])) unset($out['any']);
		if (empty($out['must_not'])) unset($out['must_not']);

		return $out;
	}

	protected function mergeFilterGroup(mixed $a, mixed $b): array {
		$out = is_array($a) ? $a : [];
		if (!is_array($b)) return $out;

		foreach ($b as $key => $value) {
			if (!isset($out[$key])) {
				$out[$key] = $value;
			} else {
				$out[$key] = $this->mergeFieldConstraint($out[$key], $value);
			}
		}
		return $out;
	}

	protected function mergeFieldConstraint(mixed $a, mixed $b): mixed {
		if (!is_array($a) && !is_array($b)) {
			return $a === $b ? $a : [$a, $b];
		}

		$aa = is_array($a) ? $a : [$a];
		$bb = is_array($b) ? $b : [$b];

		return array_values(array_unique(array_merge($aa, $bb), SORT_REGULAR));
	}

	// -------------------------------------------------
	// Helpers
	// -------------------------------------------------

	private function pickResource(array $resources, string $dock, string $class): mixed {
		$list = $resources[$dock] ?? null;
		return (is_array($list) && isset($list[0]) && $list[0] instanceof $class) ? $list[0] : null;
	}

	private function pickResources(array $resources, string $dock, string $class): array {
		$list = $resources[$dock] ?? null;
		if (!is_array($list)) return [];

		return array_values(array_filter(
			$list,
			static fn($r) => $r instanceof $class
		));
	}

	protected function log(string $message): void {
		if ($this->logger) {
			$this->logger->log('RetrievalAgentTool', '[' . $this->getId() . '] ' . $message);
		}
	}

	protected function error(string $message): array {
		$this->log('ERROR: ' . $message);
		return ['error' => $message];
	}
}
