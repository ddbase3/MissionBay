<?php declare(strict_types=1);

namespace MissionBay\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IClassMap;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentTool;

final class AgentToolTestAdminDisplay implements IDisplay {

	public function __construct(
		private readonly IClassMap $classmap,
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly IAssetResolver $assetResolver,
		private readonly ILinkTargetService $linkTargetService,
		private readonly ?IAgentContext $agentContext = null
	) {}

	public static function getName(): string {
		return 'agenttooltestadmindisplay';
	}

	public function setData($data) {
		// no-op
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$out = strtolower((string) $out);

		if($out === 'json') {
			return $this->handleJson($final);
		}

		return $this->handleHtml();
	}

	private function handleHtml(): string {
		$this->view->setPath(DIR_PLUGIN . 'MissionBay');
		$this->view->setTemplate('Display/AgentToolTestAdminDisplay.php');

		$this->view->assign(
			'service',
			$this->linkTargetService->getLink(
				[
					'name' => self::getName(),
					'out' => 'json'
				]
			)
		);

		$this->view->assign('resolve', fn($src) => $this->assetResolver->resolve((string) $src));

		return $this->view->loadTemplate();
	}

	private function handleJson(bool $final = false): string {
		$response = $this->buildJsonResponse();

		if($final && !headers_sent()) {
			header('Content-Type: application/json; charset=utf-8');
		}

		return (string) json_encode(
			$response,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildJsonResponse(): array {
		$payload = $this->request->getJsonBody();

		if(!is_array($payload)) {
			$payload = [];
		}

		$request = $this->normalizeRequest($payload);

		if($request['mode'] === 'detail') {
			return $this->buildDetailResponse($request['tool_key']);
		}

		if($request['mode'] === 'record') {
			return $this->buildRecordResponse($request['tool_key']);
		}

		if($request['mode'] === 'call_tool') {
			return $this->buildToolCallResponse($request['tool_key'], $request['function_name'], $request['arguments']);
		}

		return $this->buildPageResponse($request);
	}

	/**
	 * @param array<string, mixed> $request
	 * @return array<string, mixed>
	 */
	private function buildPageResponse(array $request): array {
		$rows = $this->buildRows();
		$rows = $this->applySearch($rows, $request['search']);
		$rows = $this->applySort($rows, $request['sort']);

		$total = count($rows);
		$pageSize = $request['pageSize'];
		$page = $request['page'];
		$totalPages = $pageSize > 0 ? (int) ceil($total / $pageSize) : 0;
		$offset = max(0, ($page - 1) * $pageSize);
		$pageRows = array_slice($rows, $offset, $pageSize);
		$data = [];

		foreach($pageRows as $row) {
			$data[] = [
				'id' => $row['id'],
				'tool_key' => $row['tool_key'],
				'name' => $row['name'],
				'class' => $row['class'],
				'description' => $row['description'],
				'function_count' => $row['function_count'],
				'function_names' => $row['function_names'],
				'categories' => $row['categories'],
				'tags' => $row['tags'],
			];
		}

		return [
			'mode' => 'page',
			'data' => $data,
			'groups' => [],
			'page' => $page,
			'pageSize' => $pageSize,
			'total' => $total,
			'totalPages' => $totalPages,
			'hasMore' => ($offset + $pageSize) < $total,
			'nextCursor' => null,
			'appliedSearch' => $request['search'],
			'appliedSort' => [$request['sort']],
			'appliedFilters' => [],
			'appliedGroup' => [],
		];
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function normalizeRequest(array $payload): array {
		$mode = 'page';
		$allowedModes = ['page', 'detail', 'record', 'call_tool'];

		if(isset($payload['mode']) && is_string($payload['mode']) && in_array($payload['mode'], $allowedModes, true)) {
			$mode = $payload['mode'];
		}

		$page = isset($payload['page']) ? (int) $payload['page'] : 1;
		$page = max(1, $page);

		$pageSize = isset($payload['pageSize']) ? (int) $payload['pageSize'] : 50;
		$pageSize = max(1, min(200, $pageSize));

		$search = '';
		if(isset($payload['search']) && is_scalar($payload['search'])) {
			$search = trim((string) $payload['search']);
		}

		$toolKey = '';
		if(isset($payload['tool_key']) && is_scalar($payload['tool_key'])) {
			$toolKey = trim((string) $payload['tool_key']);
		}

		$functionName = '';
		if(isset($payload['function_name']) && is_scalar($payload['function_name'])) {
			$functionName = trim((string) $payload['function_name']);
		}

		return [
			'mode' => $mode,
			'page' => $page,
			'pageSize' => $pageSize,
			'search' => $search,
			'sort' => $this->normalizeSort($payload['sort'] ?? null),
			'tool_key' => $toolKey,
			'function_name' => $functionName,
			'arguments' => $this->normalizeArguments($payload['arguments'] ?? []),
		];
	}

	/**
	 * @param mixed $sortPayload
	 * @return array<string, string>
	 */
	private function normalizeSort(mixed $sortPayload): array {
		$allowedKeys = [
			'name',
			'class',
			'description',
			'function_count',
			'function_names',
			'categories',
			'tags',
		];

		$sort = [
			'key' => 'name',
			'dir' => 'asc',
			'type' => 'string',
		];

		if(!is_array($sortPayload) || count($sortPayload) === 0) {
			return $sort;
		}

		$first = reset($sortPayload);

		if(!is_array($first)) {
			return $sort;
		}

		$key = isset($first['key']) ? (string) $first['key'] : 'name';
		if(!in_array($key, $allowedKeys, true)) {
			$key = 'name';
		}

		$dir = isset($first['dir']) ? strtolower((string) $first['dir']) : 'asc';
		$dir = $dir === 'desc' ? 'desc' : 'asc';
		$type = $key === 'function_count' ? 'int' : 'string';

		return [
			'key' => $key,
			'dir' => $dir,
			'type' => $type,
		];
	}

	/**
	 * @param mixed $arguments
	 * @return array<string, mixed>
	 */
	private function normalizeArguments(mixed $arguments): array {
		if($arguments instanceof \stdClass) {
			$arguments = (array) $arguments;
		}

		if(!is_array($arguments)) {
			return [];
		}

		return $arguments;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function buildRows(): array {
		$rows = [];
		$agentTools = $this->classmap->getInstances(['interface' => IAgentTool::class]);

		foreach($agentTools as $agentTool) {
			if(!$agentTool instanceof IAgentTool) {
				continue;
			}

			$definitions = $this->normalizeToolDefinitions($agentTool->getToolDefinitions());
			$functionNames = [];
			$categories = [];
			$tags = [];

			foreach($definitions as $definition) {
				if(isset($definition['name']) && is_scalar($definition['name'])) {
					$functionNames[] = (string) $definition['name'];
				}

				if(isset($definition['category']) && is_scalar($definition['category']) && trim((string) $definition['category']) !== '') {
					$categories[] = trim((string) $definition['category']);
				}

				if(isset($definition['tags']) && is_array($definition['tags'])) {
					foreach($definition['tags'] as $tag) {
						if(is_scalar($tag) && trim((string) $tag) !== '') {
							$tags[] = trim((string) $tag);
						}
					}
				}
			}

			$functionNames = array_values(array_unique($functionNames));
			$categories = array_values(array_unique($categories));
			$tags = array_values(array_unique($tags));
			sort($functionNames);
			sort($categories);
			sort($tags);

			$rows[] = [
				'id' => $agentTool->getName(),
				'tool_key' => $agentTool->getName(),
				'name' => $agentTool->getName(),
				'class' => $agentTool::class,
				'description' => method_exists($agentTool, 'getDescription') ? (string) $agentTool->getDescription() : '',
				'function_count' => count($definitions),
				'function_names' => implode(', ', $functionNames),
				'categories' => implode(', ', $categories),
				'tags' => implode(', ', $tags),
				'functions' => $definitions,
				'tool_definitions_pretty' => $this->encodePrettyJson($agentTool->getToolDefinitions()),
				'tool_definitions_json' => $this->encodeJson($agentTool->getToolDefinitions()),
			];
		}

		return $rows;
	}

	/**
	 * @param mixed $definitions
	 * @return array<int, array<string, mixed>>
	 */
	private function normalizeToolDefinitions(mixed $definitions): array {
		if(!is_array($definitions)) {
			return [];
		}

		$out = [];

		foreach($definitions as $definition) {
			if(!is_array($definition)) {
				continue;
			}

			$function = isset($definition['function']) && is_array($definition['function']) ? $definition['function'] : [];
			$name = '';

			if(isset($function['name']) && is_scalar($function['name'])) {
				$name = trim((string) $function['name']);
			}
			else if(isset($definition['name']) && is_scalar($definition['name'])) {
				$name = trim((string) $definition['name']);
			}

			if($name === '') {
				continue;
			}

			$parameters = isset($function['parameters']) && is_array($function['parameters']) ? $function['parameters'] : [];

			$out[] = [
				'name' => $name,
				'label' => isset($definition['label']) && is_scalar($definition['label']) ? (string) $definition['label'] : $name,
				'description' => isset($function['description']) && is_scalar($function['description']) ? (string) $function['description'] : '',
				'category' => isset($definition['category']) && is_scalar($definition['category']) ? (string) $definition['category'] : '',
				'tags' => isset($definition['tags']) && is_array($definition['tags']) ? array_values($definition['tags']) : [],
				'priority' => isset($definition['priority']) && is_scalar($definition['priority']) ? (int) $definition['priority'] : 0,
				'parameters' => $this->normalizeForJson($parameters),
				'raw' => $this->normalizeForJson($definition),
			];
		}

		usort($out, function(array $left, array $right): int {
			$priorityCompare = ((int) ($right['priority'] ?? 0)) <=> ((int) ($left['priority'] ?? 0));

			if($priorityCompare !== 0) {
				return $priorityCompare;
			}

			return strcmp($this->toLower((string) ($left['name'] ?? '')), $this->toLower((string) ($right['name'] ?? '')));
		});

		return $out;
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 * @return array<int, array<string, mixed>>
	 */
	private function applySearch(array $rows, string $search): array {
		if($search === '') {
			return $rows;
		}

		$needle = $this->toLower($search);
		$result = [];

		foreach($rows as $row) {
			$haystack = implode("\n", [
				(string) ($row['tool_key'] ?? ''),
				(string) ($row['name'] ?? ''),
				(string) ($row['class'] ?? ''),
				(string) ($row['description'] ?? ''),
				(string) ($row['function_names'] ?? ''),
				(string) ($row['categories'] ?? ''),
				(string) ($row['tags'] ?? ''),
				(string) ($row['tool_definitions_json'] ?? ''),
			]);

			if(str_contains($this->toLower($haystack), $needle)) {
				$result[] = $row;
			}
		}

		return $result;
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 * @param array<string, string> $sort
	 * @return array<int, array<string, mixed>>
	 */
	private function applySort(array $rows, array $sort): array {
		$key = $sort['key'] ?? 'name';
		$dir = $sort['dir'] ?? 'asc';

		usort($rows, function(array $left, array $right) use ($key, $dir): int {
			$leftValue = $left[$key] ?? null;
			$rightValue = $right[$key] ?? null;

			if($key === 'function_count') {
				$result = ((int) $leftValue) <=> ((int) $rightValue);
			}
			else {
				$result = strcmp($this->toLower((string) $leftValue), $this->toLower((string) $rightValue));
			}

			if($result === 0) {
				$result = strcmp($this->toLower((string) ($left['name'] ?? '')), $this->toLower((string) ($right['name'] ?? '')));
			}

			return $dir === 'desc' ? -$result : $result;
		});

		return $rows;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildDetailResponse(string $toolKey): array {
		$row = $this->findRow($toolKey);

		if($row === null) {
			return [
				'mode' => 'detail',
				'found' => false,
				'detail' => null,
			];
		}

		return [
			'mode' => 'detail',
			'found' => true,
			'detail' => [
				'id' => $row['id'],
				'tool_key' => $row['tool_key'],
				'headline' => $row['name'],
				'summary' => $row['class'],
				'description' => $row['description'],
				'function_count' => $row['function_count'],
				'function_names' => $row['function_names'],
				'badges' => [
					'Functions: ' . (string) $row['function_count'],
				],
				'functions' => $row['functions'],
				'tool_definitions_json' => $row['tool_definitions_pretty'],
			]
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildRecordResponse(string $toolKey): array {
		$row = $this->findRow($toolKey);

		return [
			'mode' => 'record',
			'found' => $row !== null,
			'record' => $row !== null ? $this->normalizeForJson($row) : null,
		];
	}

	/**
	 * @param array<string, mixed> $arguments
	 * @return array<string, mixed>
	 */
	private function buildToolCallResponse(string $toolKey, string $functionName, array $arguments): array {
		if($toolKey === '' || $functionName === '') {
			return [
				'mode' => 'call_tool',
				'ok' => false,
				'error' => 'Missing tool_key or function_name.',
			];
		}

		$tool = $this->findTool($toolKey);

		if(!$tool instanceof IAgentTool) {
			return [
				'mode' => 'call_tool',
				'ok' => false,
				'error' => 'Tool not found: ' . $toolKey,
			];
		}

		if(!$this->hasFunction($tool, $functionName)) {
			return [
				'mode' => 'call_tool',
				'ok' => false,
				'error' => 'Function not found on tool: ' . $functionName,
			];
		}

		if(!$this->agentContext instanceof IAgentContext) {
			return [
				'mode' => 'call_tool',
				'ok' => false,
				'tool_key' => $toolKey,
				'function_name' => $functionName,
				'arguments' => $this->normalizeForJson($arguments),
				'error' => 'No IAgentContext is available in the display. The form works, but callTool cannot be executed without a context instance.',
			];
		}

		try {
			$result = $tool->callTool($functionName, $arguments, $this->agentContext);

			return [
				'mode' => 'call_tool',
				'ok' => true,
				'tool_key' => $toolKey,
				'function_name' => $functionName,
				'arguments' => $this->normalizeForJson($arguments),
				'result' => $this->normalizeForJson($result),
			];
		}
		catch(\Throwable $e) {
			return [
				'mode' => 'call_tool',
				'ok' => false,
				'tool_key' => $toolKey,
				'function_name' => $functionName,
				'arguments' => $this->normalizeForJson($arguments),
				'error' => $e->getMessage(),
				'exception' => $e::class,
			];
		}
	}

	private function hasFunction(IAgentTool $tool, string $functionName): bool {
		foreach($this->normalizeToolDefinitions($tool->getToolDefinitions()) as $definition) {
			if((string) ($definition['name'] ?? '') === $functionName) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function findRow(string $toolKey): ?array {
		if($toolKey === '') {
			return null;
		}

		foreach($this->buildRows() as $row) {
			if((string) $row['tool_key'] === $toolKey || (string) $row['id'] === $toolKey) {
				return $row;
			}
		}

		return null;
	}

	private function findTool(string $toolKey): ?IAgentTool {
		if($toolKey === '') {
			return null;
		}

		$agentTools = $this->classmap->getInstances(['interface' => IAgentTool::class]);

		foreach($agentTools as $agentTool) {
			if(!$agentTool instanceof IAgentTool) {
				continue;
			}

			if($agentTool->getName() === $toolKey) {
				return $agentTool;
			}
		}

		return null;
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	private function normalizeForJson(mixed $value, int $depth = 0): mixed {
		if($depth > 20) {
			return '[max-depth]';
		}

		if($value === null || is_scalar($value)) {
			return $value;
		}

		if(is_array($value)) {
			$out = [];

			foreach($value as $key => $item) {
				$out[$key] = $this->normalizeForJson($item, $depth + 1);
			}

			return $out;
		}

		if($value instanceof \JsonSerializable) {
			return $this->normalizeForJson($value->jsonSerialize(), $depth + 1);
		}

		if($value instanceof \stdClass) {
			return $this->normalizeForJson((array) $value, $depth + 1);
		}

		if(is_object($value)) {
			$out = [
				'__class' => $value::class,
			];

			if(method_exists($value, '__toString')) {
				$out['__string'] = (string) $value;
			}

			return $out;
		}

		if(is_resource($value)) {
			return '[resource]';
		}

		return '[unsupported]';
	}

	/**
	 * @param mixed $value
	 */
	private function encodeJson(mixed $value): string {
		$json = json_encode($this->normalizeForJson($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return is_string($json) ? $json : 'null';
	}

	/**
	 * @param mixed $value
	 */
	private function encodePrettyJson(mixed $value): string {
		$json = json_encode($this->normalizeForJson($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

		return is_string($json) ? $json : 'null';
	}

	private function toLower(string $value): string {
		if(function_exists('mb_strtolower')) {
			return mb_strtolower($value);
		}

		return strtolower($value);
	}
}
