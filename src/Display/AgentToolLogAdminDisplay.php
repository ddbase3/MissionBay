<?php declare(strict_types=1);

namespace MissionBay\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IClassMap;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Database\Api\IDatabase;
use Base3\LinkTarget\Api\ILinkTargetService;
use MissionBay\Api\IAgentTool;

final class AgentToolLogAdminDisplay implements IDisplay {

	private const TABLE_NAME = 'base3_missionbay_tooluse';

	private ?bool $toolUseTableExists = null;

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly IAssetResolver $assetResolver,
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
		$this->view->assign('toolOptions', $this->getToolOptions());
		$this->view->assign('statusOptions', $this->getStatusOptions());
		$this->view->assign('groupOptions', $this->getGroupOptions());

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

		if($request['mode'] === 'grouped-detail') {
			return $this->buildGroupedDetailResponse($request);
		}

		if($request['mode'] === 'group-records') {
			return $this->buildGroupRecordsResponse($request);
		}

		if($request['mode'] === 'grouped-page' && count($request['group']) > 0) {
			return $this->buildGroupedPageResponse($request);
		}

		return $this->buildPageResponse($request);
	}

	/**
	 * @param array<string, mixed> $request
	 * @return array<string, mixed>
	 */
	private function buildPageResponse(array $request): array {
		if(!$this->hasToolUseTable()) {
			return $this->buildEmptyPageResponse($request);
		}

		$whereParts = $this->buildWhereParts($request['search'], $request['filters']);
		$whereSql = count($whereParts) > 0
			? ' WHERE ' . implode(' AND ', $whereParts)
			: '';

		$totalQuery = 'SELECT COUNT(*) FROM `' . self::TABLE_NAME . '` t' . $whereSql;
		$total = (int) ($this->safeScalarQuery($totalQuery, 0) ?? 0);

		$pageSize = $request['pageSize'];
		$page = $request['page'];
		$totalPages = $pageSize > 0 ? (int) ceil($total / $pageSize) : 0;
		$offset = max(0, ($page - 1) * $pageSize);

		$query =
			'SELECT ' .
			implode(', ', $this->buildSelectList(false)) .
			' FROM `' . self::TABLE_NAME . '` t' .
			$whereSql .
			$this->buildOrderBySql($request['sort']) .
			' LIMIT ' . $offset . ', ' . $pageSize;

		$rows = $this->safeMultiQuery($query);
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
	 * @param array<string, mixed> $request
	 * @return array<string, mixed>
	 */
	private function buildGroupedPageResponse(array $request): array {
		if(!$this->hasToolUseTable()) {
			return $this->buildEmptyGroupedPageResponse($request);
		}

		$group = $request['group'];
		$whereParts = $this->buildWhereParts($request['search'], $request['filters']);
		$whereSql = count($whereParts) > 0
			? ' WHERE ' . implode(' AND ', $whereParts)
			: '';

		$groupSelectList = [];
		$groupByList = [];

		foreach($group as $groupItem) {
			$key = $groupItem['key'];
			$column = $this->getColumnSql($key);
			$groupSelectList[] = $column . ' AS `' . $key . '`';
			$groupByList[] = $column;
		}

		$groupBySql = ' GROUP BY ' . implode(', ', $groupByList);

		$totalQuery =
			'SELECT COUNT(*) FROM (' .
			'SELECT 1 FROM `' . self::TABLE_NAME . '` t' .
			$whereSql .
			$groupBySql .
			') grouped_rows';

		$total = (int) ($this->safeScalarQuery($totalQuery, 0) ?? 0);

		$pageSize = $request['pageSize'];
		$page = $request['page'];
		$totalPages = $pageSize > 0 ? (int) ceil($total / $pageSize) : 0;
		$offset = max(0, ($page - 1) * $pageSize);

		$query =
			'SELECT ' .
			implode(', ', $groupSelectList) . ', ' .
			'COUNT(*) AS `group_count`, ' .
			'MIN(t.`id`) AS `group_anchor_id`, ' .
			'MAX(t.`created_at`) AS `group_last_created`, ' .
			'MAX(COALESCE(t.`finished_at`, t.`updated_at`)) AS `group_last_changed`, ' .
			'SUM(CASE WHEN t.`status` = \'finished\' THEN 1 ELSE 0 END) AS `group_finished_count`, ' .
			'SUM(CASE WHEN t.`status` = \'failed\' OR t.`status` = \'error\' THEN 1 ELSE 0 END) AS `group_error_count`, ' .
			'SUM(TIMESTAMPDIFF(SECOND, t.`created_at`, COALESCE(t.`finished_at`, t.`updated_at`))) AS `group_duration_sum`, ' .
			'GROUP_CONCAT(DISTINCT t.`tool_name` ORDER BY t.`tool_name` SEPARATOR \', \') AS `group_tools_preview`, ' .
			'GROUP_CONCAT(DISTINCT t.`user_login` ORDER BY t.`user_login` SEPARATOR \', \') AS `group_users_preview` ' .
			'FROM `' . self::TABLE_NAME . '` t' .
			$whereSql .
			$groupBySql .
			$this->buildGroupedOrderBySql($request['sort'], $group) .
			' LIMIT ' . $offset . ', ' . $pageSize;

		$rows = $this->safeMultiQuery($query);
		$data = [];

		foreach($rows as $row) {
			if(!is_array($row)) {
				continue;
			}

			$data[] = $this->normalizeGroupRow($row, $group);
		}

		return [
			'mode' => 'grouped-page',
			'data' => $data,
			'groups' => $group,
			'page' => $page,
			'pageSize' => $pageSize,
			'total' => $total,
			'totalPages' => $totalPages,
			'hasMore' => ($offset + $pageSize) < $total,
			'nextCursor' => null,
			'appliedSearch' => $request['search'],
			'appliedSort' => [$request['sort']],
			'appliedFilters' => $request['filters'],
			'appliedGroup' => $group,
		];
	}

	/**
	 * @param array<string, mixed> $request
	 * @return array<string, mixed>
	 */
	private function buildEmptyPageResponse(array $request): array {
		return [
			'mode' => 'page',
			'data' => [],
			'groups' => [],
			'page' => $request['page'],
			'pageSize' => $request['pageSize'],
			'total' => 0,
			'totalPages' => 0,
			'hasMore' => false,
			'nextCursor' => null,
			'appliedSearch' => $request['search'],
			'appliedSort' => [$request['sort']],
			'appliedFilters' => $request['filters'],
			'appliedGroup' => [],
		];
	}

	/**
	 * @param array<string, mixed> $request
	 * @return array<string, mixed>
	 */
	private function buildEmptyGroupedPageResponse(array $request): array {
		return [
			'mode' => 'grouped-page',
			'data' => [],
			'groups' => $request['group'],
			'page' => $request['page'],
			'pageSize' => $request['pageSize'],
			'total' => 0,
			'totalPages' => 0,
			'hasMore' => false,
			'nextCursor' => null,
			'appliedSearch' => $request['search'],
			'appliedSort' => [$request['sort']],
			'appliedFilters' => $request['filters'],
			'appliedGroup' => $request['group'],
		];
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function normalizeRequest(array $payload): array {
		$mode = 'page';

		if(isset($payload['mode']) && is_string($payload['mode']) && in_array($payload['mode'], ['page', 'grouped-page', 'grouped-detail', 'group-records', 'detail', 'record'], true)) {
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
		$group = $this->normalizeGroup($payload['group'] ?? null);
		$groupValues = $this->normalizeGroupValues($payload['groupValues'] ?? null);

		return [
			'mode' => $mode,
			'page' => $page,
			'pageSize' => $pageSize,
			'search' => $search,
			'id' => max(0, $id),
			'sort' => $sort,
			'filters' => $filters,
			'group' => $group,
			'groupValues' => $groupValues,
		];
	}

	/**
	 * @param mixed $sortPayload
	 * @return array<string, string>
	 */
	private function normalizeSort(mixed $sortPayload): array {
		$allowedKeys = [
			'id',
			'turn_id',
			'tool_name',
			'label',
			'node_id',
			'call_id',
			'call_index',
			'iteration',
			'status',
			'chatbot_key',
			'config_group',
			'config_name',
			'user_id',
			'user_login',
			'created_at',
			'updated_at',
			'finished_at',
			'duration_seconds',
			'group_count',
			'group_last_created',
			'group_last_changed',
			'group_finished_count',
			'group_error_count',
			'group_duration_sum',
			'group_tools_preview',
			'group_users_preview',
			'group_anchor_id',
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

		$type = in_array($key, ['id', 'call_index', 'iteration', 'duration_seconds', 'user_id', 'group_count', 'group_finished_count', 'group_error_count', 'group_duration_sum', 'group_anchor_id'], true) ? 'int' : 'string';
		if(in_array($key, ['created_at', 'updated_at', 'finished_at', 'group_last_created', 'group_last_changed'], true)) {
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
			'turn_id' => '',
			'chatbot_key' => '',
			'config_name' => '',
			'user' => '',
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
	 * @param mixed $groupPayload
	 * @return array<int, array<string, string>>
	 */
	private function normalizeGroup(mixed $groupPayload): array {
		$result = [];
		$used = [];

		if(!is_array($groupPayload)) {
			return [];
		}

		foreach($groupPayload as $item) {
			if(!is_array($item)) {
				continue;
			}

			$key = isset($item['key']) && is_scalar($item['key'])
				? trim((string) $item['key'])
				: '';

			if($key === '' || !$this->isAllowedGroupKey($key) || isset($used[$key])) {
				continue;
			}

			$dir = isset($item['dir']) && strtolower((string) $item['dir']) === 'desc'
				? 'desc'
				: 'asc';

			$result[] = [
				'key' => $key,
				'dir' => $dir,
			];

			$used[$key] = true;
		}

		return $result;
	}

	/**
	 * @param mixed $payload
	 * @return array<string, mixed>
	 */
	private function normalizeGroupValues(mixed $payload): array {
		if(!is_array($payload)) {
			return [];
		}

		$result = [];

		foreach($payload as $key => $value) {
			if(!is_string($key) || !$this->isAllowedGroupKey($key)) {
				continue;
			}

			if(is_scalar($value) || $value === null) {
				$result[$key] = $value;
			}
		}

		return $result;
	}

	private function isAllowedGroupKey(string $key): bool {
		return in_array($key, array_keys($this->getGroupColumnMap()), true);
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
				'LOWER(COALESCE(t.`turn_id`, \'\')) LIKE \'' . $needle . '\'',
				'LOWER(COALESCE(t.`node_id`, \'\')) LIKE \'' . $needle . '\'',
				'LOWER(COALESCE(t.`call_id`, \'\')) LIKE \'' . $needle . '\'',
				'LOWER(COALESCE(t.`tool_name`, \'\')) LIKE \'' . $needle . '\'',
				'LOWER(COALESCE(t.`label`, \'\')) LIKE \'' . $needle . '\'',
				'LOWER(COALESCE(t.`status`, \'\')) LIKE \'' . $needle . '\'',
				'LOWER(COALESCE(t.`chatbot_key`, \'\')) LIKE \'' . $needle . '\'',
				'LOWER(COALESCE(t.`config_group`, \'\')) LIKE \'' . $needle . '\'',
				'LOWER(COALESCE(t.`config_name`, \'\')) LIKE \'' . $needle . '\'',
				'LOWER(CAST(COALESCE(t.`user_id`, 0) AS CHAR)) LIKE \'' . $needle . '\'',
				'LOWER(COALESCE(t.`user_login`, \'\')) LIKE \'' . $needle . '\'',
				'LOWER(COALESCE(t.`error_type`, \'\')) LIKE \'' . $needle . '\'',
				'LOWER(COALESCE(t.`error_code`, \'\')) LIKE \'' . $needle . '\'',
				'LOWER(COALESCE(t.`error_message`, \'\')) LIKE \'' . $needle . '\'',
				'LOWER(COALESCE(t.`prompt_text`, \'\')) LIKE \'' . $needle . '\'',
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

		if($filters['turn_id'] !== '') {
			$whereParts[] = 'LOWER(COALESCE(t.`turn_id`, \'\')) LIKE \'' . $this->database->escape('%' . $this->toLower($filters['turn_id']) . '%') . '\'';
		}

		if($filters['chatbot_key'] !== '') {
			$whereParts[] = 'LOWER(COALESCE(t.`chatbot_key`, \'\')) LIKE \'' . $this->database->escape('%' . $this->toLower($filters['chatbot_key']) . '%') . '\'';
		}

		if($filters['config_name'] !== '') {
			$whereParts[] = 'LOWER(COALESCE(t.`config_name`, \'\')) LIKE \'' . $this->database->escape('%' . $this->toLower($filters['config_name']) . '%') . '\'';
		}

		if($filters['user'] !== '') {
			$needle = $this->database->escape('%' . $this->toLower($filters['user']) . '%');
			$whereParts[] = '(' .
				'LOWER(CAST(COALESCE(t.`user_id`, 0) AS CHAR)) LIKE \'' . $needle . '\' OR ' .
				'LOWER(COALESCE(t.`user_login`, \'\')) LIKE \'' . $needle . '\'' .
				')';
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
	 * @param array<int, array<string, string>> $group
	 * @param array<string, mixed> $groupValues
	 * @return array<int, string>
	 */
	private function buildGroupWhereParts(array $group, array $groupValues): array {
		$whereParts = [];

		foreach($group as $groupItem) {
			$key = $groupItem['key'];
			$column = $this->getColumnSql($key);

			if(!array_key_exists($key, $groupValues)) {
				continue;
			}

			$value = $groupValues[$key];

			if($value === null) {
				$whereParts[] = $column . ' IS NULL';
				continue;
			}

			$whereParts[] = $column . ' = \'' . $this->database->escape((string) $value) . '\'';
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
			'turn_id' => 'LOWER(COALESCE(t.`turn_id`, \'\'))',
			'tool_name' => 'LOWER(COALESCE(t.`tool_name`, \'\'))',
			'label' => 'LOWER(COALESCE(t.`label`, \'\'))',
			'node_id' => 'LOWER(COALESCE(t.`node_id`, \'\'))',
			'call_id' => 'LOWER(COALESCE(t.`call_id`, \'\'))',
			'call_index' => 'CAST(t.`call_index` AS SIGNED)',
			'iteration' => 'CAST(t.`iteration` AS SIGNED)',
			'status' => 'LOWER(COALESCE(t.`status`, \'\'))',
			'chatbot_key' => 'LOWER(COALESCE(t.`chatbot_key`, \'\'))',
			'config_group' => 'LOWER(COALESCE(t.`config_group`, \'\'))',
			'config_name' => 'LOWER(COALESCE(t.`config_name`, \'\'))',
			'user_id' => 'CAST(t.`user_id` AS SIGNED)',
			'user_login' => 'LOWER(COALESCE(t.`user_login`, \'\'))',
			'created_at' => 't.`created_at`',
			'updated_at' => 't.`updated_at`',
			'finished_at' => 't.`finished_at`',
			'duration_seconds' => 'TIMESTAMPDIFF(SECOND, t.`created_at`, COALESCE(t.`finished_at`, t.`updated_at`))',
		];

		$orderExpression = $map[$key] ?? $map['created_at'];

		return ' ORDER BY ' . $orderExpression . ' ' . $dir . ', t.`id` DESC';
	}

	/**
	 * @param array<string, string> $sort
	 * @param array<int, array<string, string>> $group
	 */
	private function buildGroupedOrderBySql(array $sort, array $group): string {
		$key = $sort['key'] ?? '';
		$dir = strtoupper($sort['dir'] ?? 'ASC');
		$dir = $dir === 'DESC' ? 'DESC' : 'ASC';

		$groupKeys = array_map(fn($item) => $item['key'], $group);

		if(in_array($key, $groupKeys, true)) {
			return ' ORDER BY `' . $key . '` ' . $dir . ', `group_anchor_id` DESC';
		}

		$map = [
			'created_at' => '`group_last_created`',
			'updated_at' => '`group_last_changed`',
			'finished_at' => '`group_last_changed`',
			'duration_seconds' => '`group_duration_sum`',
			'id' => '`group_anchor_id`',
			'group_count' => '`group_count`',
			'group_last_created' => '`group_last_created`',
			'group_last_changed' => '`group_last_changed`',
			'group_finished_count' => '`group_finished_count`',
			'group_error_count' => '`group_error_count`',
			'group_duration_sum' => '`group_duration_sum`',
			'group_tools_preview' => 'LOWER(COALESCE(`group_tools_preview`, \'\'))',
			'group_users_preview' => 'LOWER(COALESCE(`group_users_preview`, \'\'))',
			'group_anchor_id' => '`group_anchor_id`',
		];

		$orderExpression = $map[$key] ?? ('`' . $group[0]['key'] . '`');

		return ' ORDER BY ' . $orderExpression . ' ' . $dir . ', `group_anchor_id` DESC';
	}

	/**
	 * @return array<int, string>
	 */
	private function buildSelectList(bool $withPayload): array {
		$list = [
			't.`id`',
			't.`turn_id`',
			't.`node_id`',
			't.`call_id`',
			't.`call_index`',
			't.`chatbot_key`',
			't.`config_group`',
			't.`config_name`',
			't.`user_id`',
			't.`user_login`',
			't.`prompt_text`',
			't.`meta_json`',
			't.`tool_name`',
			't.`label`',
			't.`iteration`',
			't.`status`',
			't.`error_type`',
			't.`error_code`',
			't.`error_message`',
			't.`created_at`',
			't.`updated_at`',
			't.`finished_at`',
			'TIMESTAMPDIFF(SECOND, t.`created_at`, COALESCE(t.`finished_at`, t.`updated_at`)) AS `duration_seconds`',
		];

		if($withPayload) {
			$list[] = 't.`arguments_json`';
			$list[] = 't.`result_json`';
		}

		return $list;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function normalizeRow(array $row): array {
		return [
			'id' => (int) ($row['id'] ?? 0),
			'is_group_row' => false,
			'turn_id' => (string) ($row['turn_id'] ?? ''),
			'node_id' => (string) ($row['node_id'] ?? ''),
			'call_id' => (string) ($row['call_id'] ?? ''),
			'call_index' => (int) ($row['call_index'] ?? 0),
			'chatbot_key' => (string) ($row['chatbot_key'] ?? ''),
			'config_group' => (string) ($row['config_group'] ?? ''),
			'config_name' => (string) ($row['config_name'] ?? ''),
			'user_id' => (int) ($row['user_id'] ?? 0),
			'user_login' => (string) ($row['user_login'] ?? ''),
			'prompt_text' => (string) ($row['prompt_text'] ?? ''),
			'meta_json' => (string) ($row['meta_json'] ?? ''),
			'tool_name' => (string) ($row['tool_name'] ?? ''),
			'label' => (string) ($row['label'] ?? ''),
			'iteration' => (int) ($row['iteration'] ?? 0),
			'status' => (string) ($row['status'] ?? ''),
			'error_type' => (string) ($row['error_type'] ?? ''),
			'error_code' => (string) ($row['error_code'] ?? ''),
			'error_message' => (string) ($row['error_message'] ?? ''),
			'created_at' => (string) ($row['created_at'] ?? ''),
			'updated_at' => (string) ($row['updated_at'] ?? ''),
			'finished_at' => (string) ($row['finished_at'] ?? ''),
			'duration_seconds' => isset($row['duration_seconds']) ? (int) $row['duration_seconds'] : null,
		];
	}

	/**
	 * @param array<string, mixed> $row
	 * @param array<int, array<string, string>> $group
	 * @return array<string, mixed>
	 */
	private function normalizeGroupRow(array $row, array $group): array {
		$groupValues = [];
		$groupLabels = [];

		foreach($group as $groupItem) {
			$key = $groupItem['key'];
			$value = $row[$key] ?? null;
			$groupValues[$key] = $value;
			$groupLabels[] = $this->getGroupLabel($key) . ': ' . $this->emptyToDash((string) $value);
		}

		$groupTitle = implode(' / ', array_map(
			fn($groupItem) => $this->emptyToDash((string) ($groupValues[$groupItem['key']] ?? '')),
			$group
		));

		$groupCount = (int) ($row['group_count'] ?? 0);
		$durationSum = (int) ($row['group_duration_sum'] ?? 0);

		return [
			'id' => 'group_' . md5(json_encode($groupValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
			'is_group_row' => true,
			'group_title' => $groupTitle !== '' ? $groupTitle : 'Grouped entries',
			'group_values' => $groupValues,
			'group_labels' => $groupLabels,
			'group_count' => $groupCount,
			'group_anchor_id' => (int) ($row['group_anchor_id'] ?? 0),
			'group_last_created' => (string) ($row['group_last_created'] ?? ''),
			'group_last_changed' => (string) ($row['group_last_changed'] ?? ''),
			'group_finished_count' => (int) ($row['group_finished_count'] ?? 0),
			'group_error_count' => (int) ($row['group_error_count'] ?? 0),
			'group_duration_sum' => $durationSum,
			'group_tools_preview' => (string) ($row['group_tools_preview'] ?? ''),
			'group_users_preview' => (string) ($row['group_users_preview'] ?? ''),
			'turn_id' => (string) ($groupValues['turn_id'] ?? ''),
			'tool_name' => (string) ($groupValues['tool_name'] ?? ''),
			'status' => (string) ($groupValues['status'] ?? ''),
			'chatbot_key' => (string) ($groupValues['chatbot_key'] ?? ''),
			'config_name' => (string) ($groupValues['config_name'] ?? ''),
			'user_login' => (string) ($groupValues['user_login'] ?? ''),
			'user_id' => isset($groupValues['user_id']) ? (int) $groupValues['user_id'] : 0,
			'created_at' => (string) ($row['group_last_created'] ?? ''),
			'updated_at' => (string) ($row['group_last_changed'] ?? ''),
			'finished_at' => (string) ($row['group_last_changed'] ?? ''),
			'duration_seconds' => $groupCount > 0 ? (int) round($durationSum / $groupCount) : null,
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

		if(!$this->hasToolUseTable()) {
			return [
				'mode' => 'detail',
				'found' => false,
				'detail' => null,
			];
		}

		$query =
			'SELECT ' .
			implode(', ', $this->buildSelectList(true)) . ' ' .
			'FROM `' . self::TABLE_NAME . '` t ' .
			'WHERE t.`id` = ' . $id . ' ' .
			'LIMIT 1';

		$row = $this->safeSingleQuery($query);

		if(!is_array($row) || count($row) === 0) {
			return [
				'mode' => 'detail',
				'found' => false,
				'detail' => null,
			];
		}

		$record = $this->normalizeRecord($row);
		$duration = $record['duration_seconds'];
		$errorMessage = trim((string) ($record['error_message'] ?? ''));

		return [
			'mode' => 'detail',
			'found' => true,
			'detail' => [
				'kind' => 'log-entry-detail',
				'id' => $record['id'],
				'headline' => $record['tool_name'],
				'summary' => $record['label'],
				'record' => $record,
				'badges' => array_values(array_filter([
					'Status: ' . $record['status'],
					'Turn: ' . $record['turn_id'],
					'Call index: ' . (string) $record['call_index'],
					'Iteration: ' . (string) $record['iteration'],
					$duration !== null ? 'Duration: ' . $duration . ' s' : null,
				])),
				'sections' => [
					[
						'label' => 'ID',
						'value' => (string) $record['id'],
					],
					[
						'label' => 'Turn ID',
						'value' => $this->emptyToDash($record['turn_id']),
					],
					[
						'label' => 'Node ID',
						'value' => $this->emptyToDash($record['node_id']),
					],
					[
						'label' => 'Call ID',
						'value' => $this->emptyToDash($record['call_id']),
					],
					[
						'label' => 'Call index',
						'value' => (string) $record['call_index'],
					],
					[
						'label' => 'Tool name',
						'value' => $this->emptyToDash($record['tool_name']),
					],
					[
						'label' => 'Label',
						'value' => $this->emptyToDash($record['label']),
					],
					[
						'label' => 'Chatbot key',
						'value' => $this->emptyToDash($record['chatbot_key']),
					],
					[
						'label' => 'Config group',
						'value' => $this->emptyToDash($record['config_group']),
					],
					[
						'label' => 'Config name',
						'value' => $this->emptyToDash($record['config_name']),
					],
					[
						'label' => 'User ID',
						'value' => (string) $record['user_id'],
					],
					[
						'label' => 'User login',
						'value' => $this->emptyToDash($record['user_login']),
					],
					[
						'label' => 'Error type',
						'value' => $this->emptyToDash($record['error_type']),
					],
					[
						'label' => 'Error code',
						'value' => $this->emptyToDash($record['error_code']),
					],
					[
						'label' => 'Error message',
						'value' => $errorMessage !== '' ? $errorMessage : '-',
					],
					[
						'label' => 'Prompt text',
						'value' => $this->emptyToDash($record['prompt_text']),
					],
					[
						'label' => 'Meta JSON',
						'value' => $this->formatJsonText((string) ($row['meta_json'] ?? '')),
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
						'value' => $this->emptyToDash($record['created_at']),
					],
					[
						'label' => 'Updated at',
						'value' => $this->emptyToDash($record['updated_at']),
					],
					[
						'label' => 'Finished at',
						'value' => $this->emptyToDash($record['finished_at']),
					],
					[
						'label' => 'Duration',
						'value' => $duration !== null ? $duration . ' s' : '-',
					],
				],
			],
		];
	}

	/**
	 * @param array<string, mixed> $request
	 * @return array<string, mixed>
	 */
	private function buildGroupedDetailResponse(array $request): array {
		if(!$this->hasToolUseTable()) {
			return $this->buildEmptyGroupedDetailResponse();
		}

		$whereParts = array_merge(
			$this->buildWhereParts($request['search'], $request['filters']),
			$this->buildGroupWhereParts($request['group'], $request['groupValues'])
		);

		$whereSql = count($whereParts) > 0
			? ' WHERE ' . implode(' AND ', $whereParts)
			: '';

		$query =
			'SELECT ' .
			implode(', ', $this->buildSelectList(false)) . ' ' .
			'FROM `' . self::TABLE_NAME . '` t' .
			$whereSql .
			' ORDER BY t.`created_at` ASC, t.`call_index` ASC, t.`id` ASC' .
			' LIMIT 0, 250';

		$rows = $this->safeMultiQuery($query);
		$children = [];

		foreach($rows as $row) {
			if(!is_array($row)) {
				continue;
			}

			$children[] = $this->normalizeRow($row);
		}

		return [
			'mode' => 'grouped-detail',
			'found' => true,
			'detail' => [
				'kind' => 'grouped-child-table',
				'headline' => 'Grouped tool calls',
				'summary' => count($children) . ' matching log entries loaded. Clipboard export uses the same filters and group values.',
				'columns' => [
					[
						'key' => 'created_at',
						'label' => 'Created',
					],
					[
						'key' => 'turn_id',
						'label' => 'Turn',
					],
					[
						'key' => 'call_index',
						'label' => 'Call',
					],
					[
						'key' => 'tool_name',
						'label' => 'Tool',
					],
					[
						'key' => 'status',
						'label' => 'Status',
					],
					[
						'key' => 'user_login',
						'label' => 'User',
					],
					[
						'key' => 'chatbot_key',
						'label' => 'Chatbot',
					],
				],
				'rows' => $children,
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildEmptyGroupedDetailResponse(): array {
		return [
			'mode' => 'grouped-detail',
			'found' => true,
			'detail' => [
				'kind' => 'grouped-child-table',
				'headline' => 'Grouped tool calls',
				'summary' => '0 matching log entries loaded. The tool log table does not exist yet.',
				'columns' => [
					[
						'key' => 'created_at',
						'label' => 'Created',
					],
					[
						'key' => 'turn_id',
						'label' => 'Turn',
					],
					[
						'key' => 'call_index',
						'label' => 'Call',
					],
					[
						'key' => 'tool_name',
						'label' => 'Tool',
					],
					[
						'key' => 'status',
						'label' => 'Status',
					],
					[
						'key' => 'user_login',
						'label' => 'User',
					],
					[
						'key' => 'chatbot_key',
						'label' => 'Chatbot',
					],
				],
				'rows' => [],
			],
		];
	}

	/**
	 * @param array<string, mixed> $request
	 * @return array<string, mixed>
	 */
	private function buildGroupRecordsResponse(array $request): array {
		if(!$this->hasToolUseTable()) {
			return [
				'mode' => 'group-records',
				'found' => false,
				'records' => [],
				'limit' => 500,
				'totalReturned' => 0,
			];
		}

		$whereParts = array_merge(
			$this->buildWhereParts($request['search'], $request['filters']),
			$this->buildGroupWhereParts($request['group'], $request['groupValues'])
		);

		$whereSql = count($whereParts) > 0
			? ' WHERE ' . implode(' AND ', $whereParts)
			: '';

		$query =
			'SELECT ' .
			implode(', ', $this->buildSelectList(true)) . ' ' .
			'FROM `' . self::TABLE_NAME . '` t' .
			$whereSql .
			' ORDER BY t.`created_at` ASC, t.`call_index` ASC, t.`id` ASC' .
			' LIMIT 0, 500';

		$rows = $this->safeMultiQuery($query);
		$records = [];

		foreach($rows as $row) {
			if(!is_array($row)) {
				continue;
			}

			$records[] = $this->normalizeRecord($row);
		}

		return [
			'mode' => 'group-records',
			'found' => count($records) > 0,
			'records' => $records,
			'limit' => 500,
			'totalReturned' => count($records),
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

		if(!$this->hasToolUseTable()) {
			return [
				'mode' => 'record',
				'found' => false,
				'record' => null,
			];
		}

		$query =
			'SELECT ' .
			implode(', ', $this->buildSelectList(true)) . ' ' .
			'FROM `' . self::TABLE_NAME . '` t ' .
			'WHERE t.`id` = ' . $id . ' ' .
			'LIMIT 1';

		$row = $this->safeSingleQuery($query);

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
		$metaJson = (string) ($row['meta_json'] ?? '');

		return [
			'id' => (int) ($row['id'] ?? 0),
			'turn_id' => (string) ($row['turn_id'] ?? ''),
			'node_id' => (string) ($row['node_id'] ?? ''),
			'call_id' => (string) ($row['call_id'] ?? ''),
			'call_index' => (int) ($row['call_index'] ?? 0),
			'chatbot_key' => (string) ($row['chatbot_key'] ?? ''),
			'config_group' => (string) ($row['config_group'] ?? ''),
			'config_name' => (string) ($row['config_name'] ?? ''),
			'user_id' => (int) ($row['user_id'] ?? 0),
			'user_login' => (string) ($row['user_login'] ?? ''),
			'prompt_text' => (string) ($row['prompt_text'] ?? ''),
			'meta' => $this->decodeJsonText($metaJson),
			'meta_json' => $metaJson,
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

	private function hasToolUseTable(): bool {
		if($this->toolUseTableExists !== null) {
			return $this->toolUseTableExists;
		}

		try {
			$this->database->connect();

			$query =
				'SELECT COUNT(*) ' .
				'FROM information_schema.tables ' .
				'WHERE table_schema = DATABASE() ' .
				'AND table_name = \'' . $this->database->escape(self::TABLE_NAME) . '\'';

			$this->toolUseTableExists = ((int) ($this->database->scalarQuery($query) ?? 0)) > 0;
		} catch(\Throwable $exception) {
			$this->toolUseTableExists = false;
		}

		return $this->toolUseTableExists;
	}

	/**
	 * @return array<int, mixed>
	 */
	private function safeMultiQuery(string $query): array {
		try {
			$rows = $this->database->multiQuery($query);
			return is_array($rows) ? $rows : [];
		} catch(\Throwable $exception) {
			$this->toolUseTableExists = false;
			return [];
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function safeSingleQuery(string $query): array {
		try {
			$row = $this->database->singleQuery($query);
			return is_array($row) ? $row : [];
		} catch(\Throwable $exception) {
			$this->toolUseTableExists = false;
			return [];
		}
	}

	private function safeScalarQuery(string $query, mixed $default = null): mixed {
		try {
			return $this->database->scalarQuery($query) ?? $default;
		} catch(\Throwable $exception) {
			$this->toolUseTableExists = false;
			return $default;
		}
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

		if($this->hasToolUseTable()) {
			$rows = $this->safeMultiQuery(
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
		$rows = $this->hasToolUseTable()
			? $this->safeMultiQuery(
				'SELECT DISTINCT t.`status` FROM `' . self::TABLE_NAME . '` t WHERE t.`status` <> \'\' ORDER BY t.`status` ASC'
			)
			: [];

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

	/**
	 * @return array<int, array<string, string>>
	 */
	private function getGroupOptions(): array {
		$options = [];

		foreach($this->getGroupColumnMap() as $key => $sql) {
			$options[] = [
				'key' => $key,
				'label' => $this->getGroupLabel($key),
			];
		}

		return $options;
	}

	/**
	 * @return array<string, string>
	 */
	private function getGroupColumnMap(): array {
		return [
			'turn_id' => 't.`turn_id`',
			'tool_name' => 't.`tool_name`',
			'status' => 't.`status`',
			'chatbot_key' => 't.`chatbot_key`',
			'config_group' => 't.`config_group`',
			'config_name' => 't.`config_name`',
			'user_id' => 't.`user_id`',
			'user_login' => 't.`user_login`',
			'node_id' => 't.`node_id`',
		];
	}

	private function getColumnSql(string $key): string {
		$map = $this->getGroupColumnMap();
		return $map[$key] ?? 't.`turn_id`';
	}

	private function getGroupLabel(string $key): string {
		$labels = [
			'turn_id' => 'Turn',
			'tool_name' => 'Tool',
			'status' => 'Status',
			'chatbot_key' => 'Chatbot',
			'config_group' => 'Config group',
			'config_name' => 'Config name',
			'user_id' => 'User ID',
			'user_login' => 'User',
			'node_id' => 'Node',
		];

		return $labels[$key] ?? $key;
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
			return '-';
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
		return trim($value) !== '' ? $value : '-';
	}

	private function toLower(string $value): string {
		if(function_exists('mb_strtolower')) {
			return mb_strtolower($value);
		}

		return strtolower($value);
	}
}
