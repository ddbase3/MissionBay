<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentTool;
use MissionBay\Api\IAgentContext;
use MissionBay\Agent\AgentNodeDock;

class ToolProxyAgentTool extends AbstractAgentResource implements IAgentTool {

	/** @var IAgentTool[] */
	private array $tools = [];

	/**
	 * Catalog:
	 * [
	 *   'function_name' => [
	 *     'tool' => IAgentTool,
	 *     'def' => array,
	 *     'name' => string,
	 *     'label' => string,
	 *     'description' => string,
	 *     'parameters' => array,
	 *     'category' => string,
	 *     'tags' => array<int, string>,
	 *     'priority' => int
	 *   ],
	 * ]
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $catalog = [];

	/** @var array<int, string> */
	private array $categories = [];

	/** @var array<string, int> */
	private array $duplicates = [];

	private bool $built = false;

	public static function getName(): string {
		return 'toolproxyagenttool';
	}

	public function getDescription(): string {
		return 'Searches and invokes tools behind a proxy using categories, tags (boost), and priority (ranking).';
	}

	public function getDockDefinitions(): array {
		return [
			new AgentNodeDock(
				name: 'tools',
				description: 'Tools behind the proxy.',
				interface: IAgentTool::class,
				maxConnections: 99,
				required: false
			)
		];
	}

	public function init(array $resources, IAgentContext $context): void {
		$this->tools = [];
		foreach (($resources['tools'] ?? []) as $tool) {
			if ($tool instanceof IAgentTool) {
				$this->tools[] = $tool;
			}
		}

		$this->catalog = [];
		$this->categories = [];
		$this->duplicates = [];
		$this->built = false;
	}

	public function getToolDefinitions(): array {
		$this->buildCatalog();

		$cats = $this->categories;
		$catsText = $cats !== [] ? implode(', ', $cats) : 'none';

		$categorySchema = [
			'type' => 'string',
			'description' => 'Required category. Keep categories small and stable.'
		];

		if ($cats !== []) {
			$categorySchema['enum'] = $cats;
			$categorySchema['description'] = 'Required category. Allowed: ' . $catsText;
		} else {
			$categorySchema['description'] = 'Required category. No categories available (proxy has no tools docked).';
		}

		return [
			[
				'type' => 'function',
				'label' => 'Tool Proxy Category List',
				'category' => 'meta',
				'tags' => ['toolproxy', 'discovery', 'catalog', 'categories'],
				'priority' => 50,
				'function' => [
					'name' => 'toolproxy_list_categories',
					'description' => 'Lists all available tool categories behind the proxy, including tool counts and top tags per category.',
					'parameters' => [
						'type' => 'object',
						'properties' => [
							'max_categories' => [
								'type' => 'integer',
								'description' => 'Optional max number of categories to return (1-100).'
							],
							'max_tags_per_category' => [
								'type' => 'integer',
								'description' => 'Optional max number of tags to return per category (0-50).'
							]
						],
						'required' => []
					]
				]
			],
			[
				'type' => 'function',
				'label' => 'Tool Proxy Search',
				'category' => 'meta',
				'tags' => ['toolproxy', 'discovery', 'search', 'tags', 'category'],
				'priority' => 50,
				'function' => [
					'name' => 'toolproxy_search',
					'description' => 'Deterministic tool discovery: filter by category; tags are ranking boost; priority is ranking. Order: tag matches (desc) -> priority (desc) -> name (asc). Categories: ' . $catsText,
					'parameters' => [
						'type' => 'object',
						'properties' => [
							'category' => $categorySchema,
							'tags' => [
								'type' => 'array',
								'items' => ['type' => 'string'],
								'description' => 'Optional tags. Tags are used for ranking (boost), not as a strict filter.'
							],
							'limit' => [
								'type' => 'integer',
								'description' => 'Max results (1-25).'
							]
						],
						'required' => ['category']
					]
				]
			],
			[
				'type' => 'function',
				'label' => 'Tool Proxy Describe',
				'category' => 'meta',
				'tags' => ['toolproxy', 'schema', 'describe', 'parameters'],
				'priority' => 50,
				'function' => [
					'name' => 'toolproxy_describe',
					'description' => 'Return full tool schema for a single tool name.',
					'parameters' => [
						'type' => 'object',
						'properties' => [
							'name' => [
								'type' => 'string',
								'description' => 'Exact tool function name.'
							]
						],
						'required' => ['name']
					]
				]
			],
			[
				'type' => 'function',
				'label' => 'Tool Proxy Call',
				'category' => 'meta',
				'tags' => ['toolproxy', 'invoke', 'routing', 'execute'],
				'priority' => 50,
				'function' => [
					'name' => 'toolproxy_call',
					'description' => 'Invoke a tool behind the proxy by name and arguments object.',
					'parameters' => [
						'type' => 'object',
						'properties' => [
							'name' => [
								'type' => 'string',
								'description' => 'Exact tool function name to invoke.'
							],
							'arguments' => [
								'type' => 'object',
								'description' => 'Tool arguments as JSON object.',
								'additionalProperties' => true
							]
						],
						'required' => ['name', 'arguments']
					]
				]
			]
		];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		$this->buildCatalog();

		if ($name === 'toolproxy_list_categories') {
			return $this->handleListCategories($arguments);
		}

		if ($name === 'toolproxy_search') {
			return $this->handleSearch($arguments);
		}

		if ($name === 'toolproxy_describe') {
			return $this->handleDescribe($arguments);
		}

		if ($name === 'toolproxy_call') {
			return $this->handleCall($arguments, $context);
		}

		throw new \InvalidArgumentException("Unsupported tool: {$name}");
	}

	private function handleListCategories(array $arguments): array {
		$maxCategories = (int)($arguments['max_categories'] ?? 50);
		$maxCategories = max(1, min(100, $maxCategories));

		$maxTags = (int)($arguments['max_tags_per_category'] ?? 8);
		$maxTags = max(0, min(50, $maxTags));

		$stats = $this->buildCategoryStats($maxTags);

		$out = [];
		foreach ($stats as $row) {
			$out[] = $row;
			if (count($out) >= $maxCategories) {
				break;
			}
		}

		return [
			'categories' => $out,
			'usage' => [
				'search' => [
					'tool' => 'toolproxy_search',
					'args' => [
						'category' => '<category_from_list>',
						'tags' => ['<optional_tag_from_list>'],
						'limit' => 5
					]
				]
			]
		];
	}

	/**
	 * Build deterministic category stats:
	 * - sorted by category name
	 * - top tags sorted by (count desc, tag asc)
	 */
	private function buildCategoryStats(int $maxTagsPerCategory): array {
		$byCategory = [];

		foreach ($this->catalog as $meta) {
			$cat = (string)($meta['category'] ?? 'misc');
			if (!isset($byCategory[$cat])) {
				$byCategory[$cat] = [
					'category' => $cat,
					'tool_count' => 0,
					'tag_counts' => []
				];
			}

			$byCategory[$cat]['tool_count']++;

			$tags = is_array($meta['tags'] ?? null) ? $meta['tags'] : [];
			foreach ($tags as $t) {
				$t = (string)$t;
				if ($t === '') {
					continue;
				}
				$byCategory[$cat]['tag_counts'][$t] = (int)($byCategory[$cat]['tag_counts'][$t] ?? 0) + 1;
			}
		}

		$cats = array_keys($byCategory);
		sort($cats);

		$out = [];
		foreach ($cats as $cat) {
			$tagCounts = $byCategory[$cat]['tag_counts'];

			$tags = [];
			if ($maxTagsPerCategory > 0 && $tagCounts !== []) {
				$tags = $this->topTags($tagCounts, $maxTagsPerCategory);
			}

			$out[] = [
				'category' => $byCategory[$cat]['category'],
				'tool_count' => $byCategory[$cat]['tool_count'],
				'top_tags' => $tags
			];
		}

		return $out;
	}

	/**
	 * @param array<string,int> $tagCounts
	 * @return array<int, array{tag:string,count:int}>
	 */
	private function topTags(array $tagCounts, int $limit): array {
		$rows = [];
		foreach ($tagCounts as $tag => $count) {
			$rows[] = ['tag' => (string)$tag, 'count' => (int)$count];
		}

		usort($rows, static function(array $a, array $b): int {
			if ($a['count'] !== $b['count']) {
				return $b['count'] <=> $a['count'];
			}
			return strcmp((string)$a['tag'], (string)$b['tag']);
		});

		return array_slice($rows, 0, $limit);
	}

	private function handleSearch(array $arguments): array {
		$category = $this->normalizeToken((string)($arguments['category'] ?? ''));
		$tags = $this->normalizeTagList($arguments['tags'] ?? []);
		$limit = (int)($arguments['limit'] ?? 5);
		$limit = max(1, min(25, $limit));

		if ($category === '') {
			return ['error' => 'Missing required parameter: category'];
		}

		if ($this->catalog === []) {
			return ['error' => 'No tools available behind proxy'];
		}

		$knownCats = array_flip($this->categories);
		if (!isset($knownCats[$category])) {
			return [
				'error' => 'Unknown category: ' . $category,
				'available_categories' => $this->categories
			];
		}

		$candidates = [];
		foreach ($this->catalog as $meta) {
			if (($meta['category'] ?? '') !== $category) {
				continue;
			}

			$matchCount = $this->countTagMatches($tags, $meta['tags'] ?? []);
			$priority = (int)($meta['priority'] ?? 0);

			$candidates[] = [
				'meta' => $meta,
				'matchCount' => $matchCount,
				'priority' => $priority
			];
		}

		// Deterministic ranking:
		// 1) tag matches (more matches first)
		// 2) priority (higher first)
		// 3) name (asc)
		usort($candidates, static function(array $a, array $b): int {
			if ($a['matchCount'] !== $b['matchCount']) {
				return $b['matchCount'] <=> $a['matchCount'];
			}
			if ($a['priority'] !== $b['priority']) {
				return $b['priority'] <=> $a['priority'];
			}
			return strcmp((string)$a['meta']['name'], (string)$b['meta']['name']);
		});

		$out = [];
		foreach (array_slice($candidates, 0, $limit) as $row) {
			$meta = $row['meta'];

			$out[] = [
				'name' => $meta['name'],
				'label' => $meta['label'],
				'description' => $meta['description'],
				'category' => $meta['category'],
				'tags' => $meta['tags'],
				'priority' => $meta['priority'],
				'signature' => $this->buildSignature($meta['name'], $meta['parameters']),
				'matching_tags' => $row['matchCount']
			];
		}

		return [
			'category' => $category,
			'tags' => $tags,
			'limit' => $limit,
			'results' => $out,
			'next' => [
				'describe' => ['tool' => 'toolproxy_describe', 'args' => ['name' => '<tool_name>']],
				'call' => ['tool' => 'toolproxy_call', 'args' => ['name' => '<tool_name>', 'arguments' => ['...']]]
			]
		];
	}

	private function handleDescribe(array $arguments): array {
		$name = trim((string)($arguments['name'] ?? ''));
		if ($name === '') {
			return ['error' => 'Missing required parameter: name'];
		}

		if (isset($this->duplicates[$name])) {
			return ['error' => 'Ambiguous tool name (duplicate). Ensure unique function names behind the proxy.'];
		}

		$meta = $this->catalog[$name] ?? null;
		if (!$meta) {
			return ['error' => 'Tool not found: ' . $name];
		}

		return [
			'name' => $meta['name'],
			'label' => $meta['label'],
			'description' => $meta['description'],
			'category' => $meta['category'],
			'tags' => $meta['tags'],
			'priority' => $meta['priority'],
			'signature' => $this->buildSignature($meta['name'], $meta['parameters']),
			'parameters' => $meta['parameters']
		];
	}

	private function handleCall(array $arguments, IAgentContext $context): array {
		$name = trim((string)($arguments['name'] ?? ''));
		$args = $arguments['arguments'] ?? [];

		if ($name === '') {
			return ['error' => 'Missing required parameter: name'];
		}
		if (!is_array($args)) {
			return ['error' => 'Parameter "arguments" must be an object'];
		}

		if (isset($this->duplicates[$name])) {
			return ['error' => 'Ambiguous tool name (duplicate). Ensure unique function names behind the proxy.'];
		}

		$meta = $this->catalog[$name] ?? null;
		if (!$meta) {
			return ['error' => 'Tool not found: ' . $name];
		}

		$this->emitSubtoolEvent($context, 'tool.started', [
			'tool' => $name,
			'label' => $meta['label'],
			'args' => $args,
			'via' => 'toolproxy'
		]);

		try {
			/** @var IAgentTool $tool */
			$tool = $meta['tool'];
			$res = $tool->callTool($name, $args, $context);

			return [
				'ok' => true,
				'tool' => $name,
				'result' => $res
			];
		} catch (\Throwable $e) {
			return [
				'ok' => false,
				'tool' => $name,
				'error' => $e->getMessage()
			];
		} finally {
			$this->emitSubtoolEvent($context, 'tool.finished', [
				'tool' => $name,
				'label' => $meta['label'],
				'via' => 'toolproxy'
			]);
		}
	}

	private function buildCatalog(): void {
		if ($this->built) {
			return;
		}

		$this->catalog = [];
		$this->categories = [];
		$this->duplicates = [];

		$categorySet = [];

		foreach ($this->tools as $tool) {
			foreach ($tool->getToolDefinitions() as $def) {
				$fn = (string)($def['function']['name'] ?? '');
				if ($fn === '') {
					continue;
				}

				// Duplicate tool function names are not allowed behind the proxy.
				if (isset($this->catalog[$fn])) {
					$this->duplicates[$fn] = ($this->duplicates[$fn] ?? 1) + 1;
					unset($this->catalog[$fn]);
					continue;
				}
				if (isset($this->duplicates[$fn])) {
					continue;
				}

				$label = (string)($def['label'] ?? $fn);
				$description = (string)($def['function']['description'] ?? '');
				$parameters = is_array($def['function']['parameters'] ?? null) ? $def['function']['parameters'] : [];

				$category = $this->normalizeToken((string)($def['category'] ?? 'misc'));
				if ($category === '') {
					$category = 'misc';
				}

				$tags = $this->normalizeTagList($def['tags'] ?? []);
				$priority = (int)($def['priority'] ?? 0);

				$this->catalog[$fn] = [
					'tool' => $tool,
					'def' => $def,
					'name' => $fn,
					'label' => $label,
					'description' => $description,
					'parameters' => $parameters,
					'category' => $category,
					'tags' => $tags,
					'priority' => $priority
				];

				$categorySet[$category] = $category;
			}
		}

		$cats = array_values($categorySet);
		sort($cats);
		$this->categories = $cats;

		$this->built = true;
	}

	private function normalizeToken(string $s): string {
		$s = trim(mb_strtolower($s));
		return preg_replace('/\s+/u', '_', $s) ?? $s;
	}

	private function normalizeTagList(mixed $tags): array {
		if (!is_array($tags)) {
			return [];
		}

		$out = [];
		foreach ($tags as $t) {
			$t = $this->normalizeToken((string)$t);
			if ($t !== '') {
				$out[] = $t;
			}
		}

		$out = array_values(array_unique($out));
		sort($out);

		return $out;
	}

	/**
	 * Counts how many of the requested tags are present in the tool tags.
	 * This is used as a ranking signal (boost), not as a strict filter.
	 *
	 * @param array<int,string> $requested
	 * @param mixed $have
	 */
	private function countTagMatches(array $requested, mixed $have): int {
		if ($requested === []) {
			return 0;
		}
		if (!is_array($have) || $have === []) {
			return 0;
		}

		$haveNorm = $this->normalizeTagList($have);
		$set = array_flip($haveNorm);

		$cnt = 0;
		foreach ($requested as $t) {
			if (isset($set[$t])) {
				$cnt++;
			}
		}

		return $cnt;
	}

	private function buildSignature(string $name, array $parameters): string {
		$props = is_array($parameters['properties'] ?? null) ? $parameters['properties'] : [];
		$required = is_array($parameters['required'] ?? null) ? $parameters['required'] : [];

		$items = [];
		foreach ($props as $key => $schema) {
			$type = $this->formatType(is_array($schema) ? $schema : []);
			$isReq = in_array($key, $required, true);
			$items[] = $isReq ? "{$key}: {$type}" : "{$key}?: {$type}";
		}

		return $name . '(' . implode(', ', $items) . ')';
	}

	private function formatType(array $schema): string {
		$type = (string)($schema['type'] ?? 'any');

		if ($type === 'array') {
			$items = is_array($schema['items'] ?? null) ? $schema['items'] : [];
			$itemType = (string)($items['type'] ?? 'any');
			return "array<{$itemType}>";
		}

		return $type;
	}

	private function emitSubtoolEvent(IAgentContext $context, string $event, array $payload): void {
		$stream = $context->getVar('eventstream');

		if (is_object($stream) && method_exists($stream, 'push')) {
			$stream->push($event, $payload);
		}
	}
}
