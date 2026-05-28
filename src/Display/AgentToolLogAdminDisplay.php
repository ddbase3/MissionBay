<?php declare(strict_types=1);

namespace MissionBay\Display;

use Base3\Api\IClassMap;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Database\Api\IDatabase;
use Base3\LinkTarget\Api\ILinkTargetService;
use MissionBay\Api\IAgentTool;

final class AgentToolLogAdminDisplay implements IDisplay {

	private const TABLE_NAME = 'base3_missionbay_tooluse';

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly IDatabase $database,
		private readonly IClassMap $classmap,
		private readonly ILinkTargetService $linkTargetService
	) {}

	public static function getName(): string {
		return 'agenttoollogadmindisplay';
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
		$this->view->setTemplate('Display/AgentToolLogAdminDisplay.php');

		$basePath = $this->getBasePath();

		$this->view->assign(
			'service',
			$this->linkTargetService->getLink(
				[
					'name' => self::getName(),
					'out' => 'json'
				]
			)
		);

		$this->view->assign('modulargridCssUrl', $basePath . '/components/Base3/ClientStack/modulargrid/styles/modulargrid.css');
		$this->view->assign('modulargridJsUrl', $basePath . '/components/Base3/ClientStack/modulargrid/index.js');
		$this->view->assign('chronoPickerCssUrl', $basePath . '/components/Base3/ClientStack/chronopicker/styles/chronopicker.css');
		$this->view->assign('chronoPickerJsUrl', $basePath . '/components/Base3/ClientStack/chronopicker/index.js');
		$this->view->assign('jsonLensCssUrl', $basePath . '/components/Base3/ClientStack/jsonlens/styles/jsonlens.css');
		$this->view->assign('jsonLensJsUrl', $basePath . '/components/Base3/ClientStack/jsonlens/index.js');
		$this->view->assign('toolOptions', $this->getToolOptions());
		$this->view->assign('statusOptions', $this->getStatusOptions());

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
			return $this->buildDetailResponse($request['id']);
		}

		if($request['mode'] === 'record') {
			return $this->buildRecordResponse($request['id']);
		}

		return $this->buildPageResponse($request);
	}

	/**
	 * @param array<string, mixed> $request
	 * @return array<string, mixed>
	 */
	private function buildPageResponse(array $request): array {
		$this->database->connect();

		$whereParts = $this->buildWhereParts($request['search'], $request['filters']);
		$whereSql = count($whereParts) > 0
			? ' WHERE ' . implode(' AND ', $whereParts)
			: '';

		$totalQuery = 'SELECT COUNT(*) FROM `' . self::TABLE_NAME . '` t' . $whereSql;
		$total = (int) ($this->database->scalarQuery($totalQuery) ?? 0);

		$pageSize = $request['pageSize'];
		$page = $request['page'];
		$totalPages = $pageSize > 0 ? (int) ceil($total / $pageSize) : 0;
		$offset = max(0, ($page - 1) * $pageSize);

		$query =
			'SELECT ' .
			implode(', ', $this->buildSelectList()) .
			' FROM `' . self::TABLE_NAME . '` t' .
			$whereSql .
			$this->buildOrderBySql($request['sort']) .
			' LIMIT ' . $offset . ', ' . $pageSize;

		$rows = $this->database->multiQuery($query);
		$data = [];

		foreach($rows as $row) {
			if(!is_array($row)) {
				continue;
			}

			$data[] = $this->normalizeRow($row);
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
			'appliedFilters' => $request['filters'],
			'appliedGroup' => [],
		];
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function normalizeRequest(array $payload): array {
		$mode = 'page';

		if(isset($payload['mode']) && is_string($payload['mode']) && in_array($payload['mode'], ['page', 'detail', 'record'], true)) {
			$mode = $payload['mode'];
		}

		$page = isset($payload['page']) ? (int) $payload['page'] : 1;
		$page = max(1, $page);

		$pageSize = isset($payload['pageSize']) ? (int) $payload['pageSize'] : 50;
		$pageSize = max(1, min(250, $pageSize));

		$search = '';
		if(isset($payload['search']) && is_scalar($payload['search'])) {
			$search = trim((string) $payload['search']);
		}

		$id = isset($payload['id']) ? (int) $payload['id'] : 0;
		$sort = $this->normalizeSort($payload['sort'] ?? null);
		$filters = $this->normalizeFilters($payload['filters'] ?? null);

		return [
			'mode' => $mode,
			'page' => $page,
			'pageSize' => $pageSize,
			'search' => $search,
			'id' => max(0, $id),
			'sort' => $sort,
			'filters' => $filters,
		];
	}

	/**
	 * @param mixed $sortPayload
	 * @return array<string, string>
	 */
	private function normalizeSort(mixed $sortPayload): array {
		$allowedKeys = [
			'id',
			'tool_name',
			'label',
			'node_id',
			'call_id',
			'iteration',
			'status',
			'created_at',
			'updated_at',
			'finished_at',
			'duration_seconds',
		];

		$sort = [
			'key' => 'created_at',
			'dir' => 'desc',
			'type' => 'datetime',
		];

		if(!is_array($sortPayload) || count($sortPayload) === 0) {
			return $sort;
		}

		$first = reset($sortPayload);

		if(!is_array($first)) {
			return $sort;
		}

		$key = isset($first['key']) ? (string) $first['key'] : 'created_at';
		if(!in_array($key, $allowedKeys, true)) {
			$key = 'created_at';
		}

		$dir = isset($first['dir']) ? strtolower((string) $first['dir']) : 'desc';
		$dir = $dir === 'asc' ? 'asc' : 'desc';

		$type = in_array($key, ['id', 'iteration', 'duration_seconds'], true) ? 'int' : 'string';
		if(in_array($key, ['created_at', 'updated_at', 'finished_at'], true)) {
			$type = 'datetime';
		}

		return [
			'key' => $key,
			'dir' => $dir,
			'type' => $type,
		];
	}

	/**
	 * @param mixed $filtersPayload
	 * @return array<string, string>
	 */
	private function normalizeFilters(mixed $filtersPayload): array {
		$result = [
			'tool_name' => '',
			'status' => '',
			'node_id' => '',
			'created_from' => '',
			'created_to' => '',
		];

		if(!is_array($filtersPayload)) {
			return $result;
		}

		foreach(array_keys($result) as $key) {
			if(isset($filtersPayload[$key]) && is_scalar($filtersPayload[$key])) {
				$result[$key] = trim((string) $filtersPayload[$key]);
			}
		}

		return $result;
	}

	/**
	 * @param string $search
	 * @param array<string, string> $filters
	 * @return array<int, string>
	 */
	private function buildWhereParts(string $search, array $filters): array {
		$whereParts = [];

		if($search !== '') {
			$needle = $this->database->escape('%' . $this->toLower($search) . '%');
			$searchParts = [
				'LOWER(CAST(t.`id` AS CHAR)) LIKE \'' . $needle . '\'',
				'LOWER(COALESCE(t.`node_id`, \'\')) LIKE \'' . $needle . '\'',
				'LOWER(COALESCE(t.`call_id`, \'\')) LIKE \'' . $needle . '\'',
				'LOWER(COALESCE(t.`tool_name`, \'\')) LIKE \'' . $needle . '\'',
				'LOWER(COALESCE(t.`label`, \'\')) LIKE \'' . $needle . '\'',
				'LOWER(COALESCE(t.`status`, \'\')) LIKE \'' . $needle . '\'',
				'LOWER(COALESCE(t.`error_type`, \'\')) LIKE \'' . $needle . '\'',
				'LOWER(COALESCE(t.`error_code`, \'\')) LIKE \'' . $needle . '\'',
				'LOWER(COALESCE(t.`error_message`, \'\')) LIKE \'' . $needle . '\'',
			];

			$whereParts[] = '(' . implode(' OR ', $searchParts) . ')';
		}

		if($filters['tool_name'] !== '') {
			$whereParts[] = 't.`tool_name` = \'' . $this->database->escape($filters['tool_name']) . '\'';
		}

		if($filters['status'] !== '') {
			$whereParts[] = 't.`status` = \'' . $this->database->escape($filters['status']) . '\'';
		}

		if($filters['node_id'] !== '') {
			$whereParts[] = 'LOWER(COALESCE(t.`node_id`, \'\')) LIKE \'' . $this->database->escape('%' . $this->toLower($filters['node_id']) . '%') . '\'';
		}

		$createdFrom = $this->normalizeDateTimeFilter($filters['created_from'], false);
		if($createdFrom !== null) {
			$whereParts[] = 't.`created_at` >= \'' . $this->database->escape($createdFrom) . '\'';
		}

		$createdTo = $this->normalizeDateTimeFilter($filters['created_to'], true);
		if($createdTo !== null) {
			$whereParts[] = 't.`created_at` <= \'' . $this->database->escape($createdTo) . '\'';
		}

		return $whereParts;
	}

	/**
	 * @param array<string, string> $sort
	 */
	private function buildOrderBySql(array $sort): string {
		$key = $sort['key'] ?? 'created_at';
		$dir = strtoupper($sort['dir'] ?? 'DESC');
		$dir = $dir === 'ASC' ? 'ASC' : 'DESC';

		$map = [
			'id' => 'CAST(t.`id` AS UNSIGNED)',
			'tool_name' => 'LOWER(COALESCE(t.`tool_name`, \'\'))',
			'label' => 'LOWER(COALESCE(t.`label`, \'\'))',
			'node_id' => 'LOWER(COALESCE(t.`node_id`, \'\'))',
			'call_id' => 'LOWER(COALESCE(t.`call_id`, \'\'))',
			'iteration' => 'CAST(t.`iteration` AS SIGNED)',
			'status' => 'LOWER(COALESCE(t.`status`, \'\'))',
			'created_at' => 't.`created_at`',
			'updated_at' => 't.`updated_at`',
			'finished_at' => 't.`finished_at`',
			'duration_seconds' => 'TIMESTAMPDIFF(SECOND, t.`created_at`, COALESCE(t.`finished_at`, t.`updated_at`))',
		];

		$orderExpression = $map[$key] ?? $map['created_at'];

		return ' ORDER BY ' . $orderExpression . ' ' . $dir . ', t.`id` DESC';
	}

	/**
	 * @return array<int, string>
	 */
	private function buildSelectList(): array {
		return [
			't.`id`',
			't.`node_id`',
			't.`call_id`',
			't.`tool_name`',
			't.`label`',
			't.`iteration`',
			't.`status`',
			't.`error_type`',
			't.`error_code`',
			't.`created_at`',
			't.`updated_at`',
			't.`finished_at`',
			'TIMESTAMPDIFF(SECOND, t.`created_at`, COALESCE(t.`finished_at`, t.`updated_at`)) AS `duration_seconds`',
		];
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function normalizeRow(array $row): array {
		return [
			'id' => (int) ($row['id'] ?? 0),
			'node_id' => (string) ($row['node_id'] ?? ''),
			'call_id' => (string) ($row['call_id'] ?? ''),
			'tool_name' => (string) ($row['tool_name'] ?? ''),
			'label' => (string) ($row['label'] ?? ''),
			'iteration' => (int) ($row['iteration'] ?? 0),
			'status' => (string) ($row['status'] ?? ''),
			'error_type' => (string) ($row['error_type'] ?? ''),
			'error_code' => (string) ($row['error_code'] ?? ''),
			'created_at' => (string) ($row['created_at'] ?? ''),
			'updated_at' => (string) ($row['updated_at'] ?? ''),
			'finished_at' => (string) ($row['finished_at'] ?? ''),
			'duration_seconds' => isset($row['duration_seconds']) ? (int) $row['duration_seconds'] : null,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildDetailResponse(int $id): array {
		if($id <= 0) {
			return [
				'mode' => 'detail',
				'found' => false,
				'detail' => null,
			];
		}

		$this->database->connect();

		$query =
			'SELECT ' .
			't.`id`, ' .
			't.`node_id`, ' .
			't.`call_id`, ' .
			't.`tool_name`, ' .
			't.`label`, ' .
			't.`iteration`, ' .
			't.`status`, ' .
			't.`arguments_json`, ' .
			't.`result_json`, ' .
			't.`error_message`, ' .
			't.`error_type`, ' .
			't.`error_code`, ' .
			't.`created_at`, ' .
			't.`updated_at`, ' .
			't.`finished_at`, ' .
			'TIMESTAMPDIFF(SECOND, t.`created_at`, COALESCE(t.`finished_at`, t.`updated_at`)) AS `duration_seconds` ' .
			'FROM `' . self::TABLE_NAME . '` t ' .
			'WHERE t.`id` = ' . $id . ' ' .
			'LIMIT 1';

		$row = $this->database->singleQuery($query);

		if(!is_array($row) || count($row) === 0) {
			return [
				'mode' => 'detail',
				'found' => false,
				'detail' => null,
			];
		}

		$duration = isset($row['duration_seconds']) ? (int) $row['duration_seconds'] : null;
		$errorMessage = trim((string) ($row['error_message'] ?? ''));

		return [
			'mode' => 'detail',
			'found' => true,
			'detail' => [
				'id' => (int) ($row['id'] ?? 0),
				'headline' => (string) ($row['tool_name'] ?? ''),
				'summary' => (string) ($row['label'] ?? ''),
				'badges' => array_values(array_filter([
					'Status: ' . (string) ($row['status'] ?? ''),
					'Iteration: ' . (string) ((int) ($row['iteration'] ?? 0)),
					$duration !== null ? 'Duration: ' . $duration . ' s' : null,
				])),
				'sections' => [
					[
						'label' => 'ID',
						'value' => (string) ((int) ($row['id'] ?? 0)),
					],
					[
						'label' => 'Node ID',
						'value' => $this->emptyToDash((string) ($row['node_id'] ?? '')),
					],
					[
						'label' => 'Call ID',
						'value' => $this->emptyToDash((string) ($row['call_id'] ?? '')),
					],
					[
						'label' => 'Tool name',
						'value' => $this->emptyToDash((string) ($row['tool_name'] ?? '')),
					],
					[
						'label' => 'Label',
						'value' => $this->emptyToDash((string) ($row['label'] ?? '')),
					],
					[
						'label' => 'Error type',
						'value' => $this->emptyToDash((string) ($row['error_type'] ?? '')),
					],
					[
						'label' => 'Error code',
						'value' => $this->emptyToDash((string) ($row['error_code'] ?? '')),
					],
					[
						'label' => 'Error message',
						'value' => $errorMessage !== '' ? $errorMessage : '—',
					],
					[
						'label' => 'Arguments JSON',
						'value' => $this->formatJsonText((string) ($row['arguments_json'] ?? '')),
					],
					[
						'label' => 'Result JSON',
						'value' => $this->formatJsonText((string) ($row['result_json'] ?? '')),
					],
				],
				'activity' => [
					[
						'label' => 'Created at',
						'value' => $this->emptyToDash((string) ($row['created_at'] ?? '')),
					],
					[
						'label' => 'Updated at',
						'value' => $this->emptyToDash((string) ($row['updated_at'] ?? '')),
					],
					[
						'label' => 'Finished at',
						'value' => $this->emptyToDash((string) ($row['finished_at'] ?? '')),
					],
					[
						'label' => 'Duration',
						'value' => $duration !== null ? $duration . ' s' : '—',
					],
				],
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildRecordResponse(int $id): array {
		if($id <= 0) {
			return [
				'mode' => 'record',
				'found' => false,
				'record' => null,
			];
		}

		$this->database->connect();

		$query =
			'SELECT ' .
			't.`id`, ' .
			't.`node_id`, ' .
			't.`call_id`, ' .
			't.`tool_name`, ' .
			't.`label`, ' .
			't.`iteration`, ' .
			't.`status`, ' .
			't.`arguments_json`, ' .
			't.`result_json`, ' .
			't.`error_message`, ' .
			't.`error_type`, ' .
			't.`error_code`, ' .
			't.`created_at`, ' .
			't.`updated_at`, ' .
			't.`finished_at`, ' .
			'TIMESTAMPDIFF(SECOND, t.`created_at`, COALESCE(t.`finished_at`, t.`updated_at`)) AS `duration_seconds` ' .
			'FROM `' . self::TABLE_NAME . '` t ' .
			'WHERE t.`id` = ' . $id . ' ' .
			'LIMIT 1';

		$row = $this->database->singleQuery($query);

		if(!is_array($row) || count($row) === 0) {
			return [
				'mode' => 'record',
				'found' => false,
				'record' => null,
			];
		}

		return [
			'mode' => 'record',
			'found' => true,
			'record' => $this->normalizeRecord($row),
		];
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function normalizeRecord(array $row): array {
		$argumentsJson = (string) ($row['arguments_json'] ?? '');
		$resultJson = (string) ($row['result_json'] ?? '');

		return [
			'id' => (int) ($row['id'] ?? 0),
			'node_id' => (string) ($row['node_id'] ?? ''),
			'call_id' => (string) ($row['call_id'] ?? ''),
			'tool_name' => (string) ($row['tool_name'] ?? ''),
			'label' => (string) ($row['label'] ?? ''),
			'iteration' => (int) ($row['iteration'] ?? 0),
			'status' => (string) ($row['status'] ?? ''),
			'arguments' => $this->decodeJsonText($argumentsJson),
			'arguments_json' => $argumentsJson,
			'result' => $this->decodeJsonText($resultJson),
			'result_json' => $resultJson,
			'error_message' => (string) ($row['error_message'] ?? ''),
			'error_type' => (string) ($row['error_type'] ?? ''),
			'error_code' => (string) ($row['error_code'] ?? ''),
			'created_at' => (string) ($row['created_at'] ?? ''),
			'updated_at' => (string) ($row['updated_at'] ?? ''),
			'finished_at' => (string) ($row['finished_at'] ?? ''),
			'duration_seconds' => isset($row['duration_seconds']) ? (int) $row['duration_seconds'] : null,
		];
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private function getToolOptions(): array {
		$values = [];

		foreach($this->classmap->getInstances(['interface' => IAgentTool::class]) as $agentTool) {
			if(!$agentTool instanceof IAgentTool) {
				continue;
			}

			$name = trim($agentTool->getName());
			if($name !== '') {
				$values[$name] = $name;
			}
		}

		$this->database->connect();

		$rows = $this->database->multiQuery(
			'SELECT DISTINCT t.`tool_name` FROM `' . self::TABLE_NAME . '` t WHERE t.`tool_name` <> \'\' ORDER BY t.`tool_name` ASC'
		);

		foreach($rows as $row) {
			if(!is_array($row)) {
				continue;
			}

			$name = trim((string) ($row['tool_name'] ?? ''));
			if($name !== '') {
				$values[$name] = $name;
			}
		}

		ksort($values);

		$options = [
			[
				'value' => '',
				'label' => 'All tools',
			]
		];

		foreach(array_values($values) as $value) {
			$options[] = [
				'value' => $value,
				'label' => $value,
			];
		}

		return $options;
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private function getStatusOptions(): array {
		$this->database->connect();

		$rows = $this->database->multiQuery(
			'SELECT DISTINCT t.`status` FROM `' . self::TABLE_NAME . '` t WHERE t.`status` <> \'\' ORDER BY t.`status` ASC'
		);

		$options = [
			[
				'value' => '',
				'label' => 'All statuses',
			]
		];

		foreach($rows as $row) {
			if(!is_array($row)) {
				continue;
			}

			$status = trim((string) ($row['status'] ?? ''));
			if($status === '') {
				continue;
			}

			$options[] = [
				'value' => $status,
				'label' => ucfirst($status),
			];
		}

		return $options;
	}

	private function normalizeDateTimeFilter(string $value, bool $isUpperBound): ?string {
		$value = trim($value);

		if($value === '') {
			return null;
		}

		if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
			return $value . ($isUpperBound ? ' 23:59:59' : ' 00:00:00');
		}

		if(preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}$/', $value) === 1) {
			return $value . ':00';
		}

		if(preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value) === 1) {
			return str_replace('T', ' ', $value) . ':00';
		}

		$timestamp = strtotime($value);
		if($timestamp === false) {
			return null;
		}

		return date('Y-m-d H:i:s', $timestamp);
	}

	private function formatJsonText(string $json): string {
		$json = trim($json);

		if($json === '') {
			return '—';
		}

		$decoded = json_decode($json, true);

		if(json_last_error() !== JSON_ERROR_NONE) {
			return $json;
		}

		return (string) json_encode(
			$decoded,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
		);
	}

	private function decodeJsonText(string $json): mixed {
		$json = trim($json);

		if($json === '') {
			return null;
		}

		$decoded = json_decode($json, true);

		if(json_last_error() !== JSON_ERROR_NONE) {
			return null;
		}

		return $decoded;
	}

	private function emptyToDash(string $value): string {
		return trim($value) !== '' ? $value : '—';
	}

	private function toLower(string $value): string {
		if(function_exists('mb_strtolower')) {
			return mb_strtolower($value);
		}

		return strtolower($value);
	}

	private function getBasePath(): string {
		$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
		$basePath = rtrim(dirname($scriptName), '/');

		if($basePath === '/' || $basePath === '\\' || $basePath === '.') {
			return '';
		}

		return $basePath;
	}
}
