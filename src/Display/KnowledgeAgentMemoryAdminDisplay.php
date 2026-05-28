<?php declare(strict_types=1);

namespace MissionBay\Display;

use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Database\Api\IDatabase;
use Base3\LinkTarget\Api\ILinkTargetService;

final class KnowledgeAgentMemoryAdminDisplay implements IDisplay {

        private const TABLE_NAME = 'base3_agent_knowledge';
        private const UPDATED_BY = 'admin';

        public function __construct(
                private readonly IRequest $request,
                private readonly IMvcView $view,
                private readonly IDatabase $database,
                private readonly ILinkTargetService $linkTargetService
        ) {}

        public static function getName(): string {
                return 'knowledgeagentmemoryadmindisplay';
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
                $this->view->setTemplate('Display/KnowledgeAgentMemoryAdminDisplay.php');

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
                $this->view->assign('memoryTypeOptions', $this->getMemoryTypeOptions());
                $this->view->assign('statusOptions', $this->getDistinctOptions('status', 'All statuses'));
                $this->view->assign('sourceOptions', $this->getDistinctOptions('source', 'All sources'));
                $this->view->assign('scopeOptions', $this->getDistinctOptions('scope', 'All scopes'));

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

                if(in_array($request['mode'], ['soft_delete', 'restore', 'lock', 'unlock', 'toggle_mutable', 'toggle_deletable'], true)) {
                        return $this->buildActionResponse($request['mode'], $request['id']);
                }

                if($request['mode'] === 'update_content') {
                        return $this->buildContentUpdateResponse($request['id'], $request['content']);
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
                $allowedModes = ['page', 'detail', 'record', 'soft_delete', 'restore', 'lock', 'unlock', 'toggle_mutable', 'toggle_deletable', 'update_content'];

                if(isset($payload['mode']) && is_string($payload['mode']) && in_array($payload['mode'], $allowedModes, true)) {
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
                $content = '';

                if(array_key_exists('content', $payload) && is_scalar($payload['content'])) {
                        $content = (string) $payload['content'];
                }

                return [
                        'mode' => $mode,
                        'page' => $page,
                        'pageSize' => $pageSize,
                        'search' => $search,
                        'id' => max(0, $id),
                        'sort' => $sort,
                        'filters' => $filters,
                        'content' => $content,
                ];
        }

        /**
         * @param mixed $sortPayload
         * @return array<string, string>
         */
        private function normalizeSort(mixed $sortPayload): array {
                $allowedKeys = [
                        'id',
                        'memory_type',
                        'memory_key',
                        'memory_subtype',
                        'status',
                        'title',
                        'source',
                        'scope',
                        'scope_ref',
                        'ident',
                        'userid',
                        'priority',
                        'confidence',
                        'valid_from',
                        'valid_to',
                        'expires_at',
                        'last_accessed_at',
                        'created_at',
                        'updated_at',
                        'summary',
                ];

                $sort = [
                        'key' => 'updated_at',
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

                $key = isset($first['key']) ? (string) $first['key'] : 'updated_at';
                if(!in_array($key, $allowedKeys, true)) {
                        $key = 'updated_at';
                }

                $dir = isset($first['dir']) ? strtolower((string) $first['dir']) : 'desc';
                $dir = $dir === 'asc' ? 'asc' : 'desc';

                $type = in_array($key, ['id', 'priority'], true) ? 'int' : 'string';
                if(in_array($key, ['confidence'], true)) {
                        $type = 'float';
                }
                if(in_array($key, ['valid_from', 'valid_to', 'expires_at', 'last_accessed_at', 'created_at', 'updated_at'], true)) {
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
                        'memory_type' => '',
                        'status' => '',
                        'source' => '',
                        'scope' => '',
                        'deleted' => 'active',
                        'locked' => '',
                        'expired' => '',
                        'memory_key' => '',
                        'ident' => '',
                        'scope_ref' => '',
                        'userid' => '',
                        'tag' => '',
                        'entity_ref' => '',
                        'created_from' => '',
                        'created_to' => '',
                        'updated_from' => '',
                        'updated_to' => '',
                ];

                if(!is_array($filtersPayload)) {
                        return $result;
                }

                foreach(array_keys($result) as $key) {
                        if(isset($filtersPayload[$key]) && is_scalar($filtersPayload[$key])) {
                                $result[$key] = trim((string) $filtersPayload[$key]);
                        }
                }

                if(!in_array($result['deleted'], ['active', 'deleted', 'all'], true)) {
                        $result['deleted'] = 'active';
                }

                if(!in_array($result['locked'], ['', 'locked', 'unlocked'], true)) {
                        $result['locked'] = '';
                }

                if(!in_array($result['expired'], ['', 'expired', 'current', 'no_expiry'], true)) {
                        $result['expired'] = '';
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
                                'LOWER(COALESCE(t.`memory_type`, \'\')) LIKE \'' . $needle . '\'',
                                'LOWER(COALESCE(t.`memory_key`, \'\')) LIKE \'' . $needle . '\'',
                                'LOWER(COALESCE(t.`memory_subtype`, \'\')) LIKE \'' . $needle . '\'',
                                'LOWER(COALESCE(t.`status`, \'\')) LIKE \'' . $needle . '\'',
                                'LOWER(COALESCE(t.`title`, \'\')) LIKE \'' . $needle . '\'',
                                'LOWER(COALESCE(t.`summary`, \'\')) LIKE \'' . $needle . '\'',
                                'LOWER(COALESCE(t.`content`, \'\')) LIKE \'' . $needle . '\'',
                                'LOWER(COALESCE(t.`tags_json`, \'\')) LIKE \'' . $needle . '\'',
                                'LOWER(COALESCE(t.`entity_refs_json`, \'\')) LIKE \'' . $needle . '\'',
                                'LOWER(COALESCE(t.`meta_json`, \'\')) LIKE \'' . $needle . '\'',
                                'LOWER(COALESCE(t.`source`, \'\')) LIKE \'' . $needle . '\'',
                                'LOWER(COALESCE(t.`scope`, \'\')) LIKE \'' . $needle . '\'',
                                'LOWER(COALESCE(t.`scope_ref`, \'\')) LIKE \'' . $needle . '\'',
                                'LOWER(COALESCE(t.`ident`, \'\')) LIKE \'' . $needle . '\'',
                                'LOWER(COALESCE(t.`userid`, \'\')) LIKE \'' . $needle . '\'',
                                'LOWER(COALESCE(t.`session`, \'\')) LIKE \'' . $needle . '\'',
                        ];

                        $whereParts[] = '(' . implode(' OR ', $searchParts) . ')';
                }

                if($filters['memory_type'] !== '') {
                        $whereParts[] = 't.`memory_type` = \'' . $this->database->escape($filters['memory_type']) . '\'';
                }

                if($filters['status'] !== '') {
                        $whereParts[] = 't.`status` = \'' . $this->database->escape($filters['status']) . '\'';
                }

                if($filters['source'] !== '') {
                        $whereParts[] = 't.`source` = \'' . $this->database->escape($filters['source']) . '\'';
                }

                if($filters['scope'] !== '') {
                        $whereParts[] = 't.`scope` = \'' . $this->database->escape($filters['scope']) . '\'';
                }

                if($filters['deleted'] === 'active') {
                        $whereParts[] = 't.`is_deleted` = 0';
                }
                else if($filters['deleted'] === 'deleted') {
                        $whereParts[] = 't.`is_deleted` = 1';
                }

                if($filters['locked'] === 'locked') {
                        $whereParts[] = 't.`is_locked` = 1';
                }
                else if($filters['locked'] === 'unlocked') {
                        $whereParts[] = 't.`is_locked` = 0';
                }

                if($filters['expired'] === 'expired') {
                        $whereParts[] = 't.`expires_at` IS NOT NULL AND t.`expires_at` < NOW()';
                }
                else if($filters['expired'] === 'current') {
                        $whereParts[] = '(t.`expires_at` IS NULL OR t.`expires_at` >= NOW())';
                }
                else if($filters['expired'] === 'no_expiry') {
                        $whereParts[] = 't.`expires_at` IS NULL';
                }

                foreach(['memory_key', 'ident', 'scope_ref', 'userid'] as $key) {
                        if($filters[$key] !== '') {
                                $whereParts[] = 'LOWER(COALESCE(t.`' . $key . '`, \'\')) LIKE \'' . $this->database->escape('%' . $this->toLower($filters[$key]) . '%') . '\'';
                        }
                }

                if($filters['tag'] !== '') {
                        $whereParts[] = 'LOWER(COALESCE(t.`tags_json`, \'\')) LIKE \'' . $this->database->escape('%' . $this->toLower($filters['tag']) . '%') . '\'';
                }

                if($filters['entity_ref'] !== '') {
                        $whereParts[] = 'LOWER(COALESCE(t.`entity_refs_json`, \'\')) LIKE \'' . $this->database->escape('%' . $this->toLower($filters['entity_ref']) . '%') . '\'';
                }

                $createdFrom = $this->normalizeDateTimeFilter($filters['created_from'], false);
                if($createdFrom !== null) {
                        $whereParts[] = 't.`created_at` >= \'' . $this->database->escape($createdFrom) . '\'';
                }

                $createdTo = $this->normalizeDateTimeFilter($filters['created_to'], true);
                if($createdTo !== null) {
                        $whereParts[] = 't.`created_at` <= \'' . $this->database->escape($createdTo) . '\'';
                }

                $updatedFrom = $this->normalizeDateTimeFilter($filters['updated_from'], false);
                if($updatedFrom !== null) {
                        $whereParts[] = 't.`updated_at` >= \'' . $this->database->escape($updatedFrom) . '\'';
                }

                $updatedTo = $this->normalizeDateTimeFilter($filters['updated_to'], true);
                if($updatedTo !== null) {
                        $whereParts[] = 't.`updated_at` <= \'' . $this->database->escape($updatedTo) . '\'';
                }

                return $whereParts;
        }

        /**
         * @param array<string, string> $sort
         */
        private function buildOrderBySql(array $sort): string {
                $key = $sort['key'] ?? 'updated_at';
                $dir = strtoupper($sort['dir'] ?? 'DESC');
                $dir = $dir === 'ASC' ? 'ASC' : 'DESC';

                $map = [
                        'id' => 'CAST(t.`id` AS UNSIGNED)',
                        'memory_type' => 'LOWER(COALESCE(t.`memory_type`, \'\'))',
                        'memory_key' => 'LOWER(COALESCE(t.`memory_key`, \'\'))',
                        'memory_subtype' => 'LOWER(COALESCE(t.`memory_subtype`, \'\'))',
                        'status' => 'LOWER(COALESCE(t.`status`, \'\'))',
                        'title' => 'LOWER(COALESCE(t.`title`, \'\'))',
                        'source' => 'LOWER(COALESCE(t.`source`, \'\'))',
                        'scope' => 'LOWER(COALESCE(t.`scope`, \'\'))',
                        'scope_ref' => 'LOWER(COALESCE(t.`scope_ref`, \'\'))',
                        'ident' => 'LOWER(COALESCE(t.`ident`, \'\'))',
                        'userid' => 'LOWER(COALESCE(t.`userid`, \'\'))',
                        'priority' => 'CAST(t.`priority` AS SIGNED)',
                        'confidence' => 'CAST(COALESCE(t.`confidence`, 0) AS DECIMAL(10,4))',
                        'valid_from' => 't.`valid_from`',
                        'valid_to' => 't.`valid_to`',
                        'expires_at' => 't.`expires_at`',
                        'last_accessed_at' => 't.`last_accessed_at`',
                        'created_at' => 't.`created_at`',
                        'updated_at' => 't.`updated_at`',
                        'summary' => 'LOWER(COALESCE(t.`summary`, \'\'))',
                ];

                $orderExpression = $map[$key] ?? $map['updated_at'];

                return ' ORDER BY ' . $orderExpression . ' ' . $dir . ', t.`id` DESC';
        }

        /**
         * @return array<int, string>
         */
        private function buildSelectList(): array {
                return [
                        't.`id`',
                        't.`memory_type`',
                        't.`memory_key`',
                        't.`memory_subtype`',
                        't.`status`',
                        't.`title`',
                        't.`summary`',
                        't.`tags_json`',
                        't.`entity_refs_json`',
                        't.`meta_json`',
                        't.`source`',
                        't.`scope`',
                        't.`scope_ref`',
                        't.`ident`',
                        't.`userid`',
                        't.`session`',
                        't.`is_locked`',
                        't.`is_mutable_by_llm`',
                        't.`is_deletable_by_llm`',
                        't.`is_deleted`',
                        't.`priority`',
                        't.`confidence`',
                        't.`valid_from`',
                        't.`valid_to`',
                        't.`expires_at`',
                        't.`last_accessed_at`',
                        't.`created_by`',
                        't.`updated_by`',
                        't.`created_at`',
                        't.`updated_at`',
                        'CASE WHEN t.`expires_at` IS NOT NULL AND t.`expires_at` < NOW() THEN 1 ELSE 0 END AS `is_expired`',
                ];
        }

        /**
         * @param array<string, mixed> $row
         * @return array<string, mixed>
         */
        private function normalizeRow(array $row): array {
                $tags = $this->decodeJsonArray((string) ($row['tags_json'] ?? ''));
                $entityRefs = $this->decodeJsonArray((string) ($row['entity_refs_json'] ?? ''));
                $meta = $this->decodeJsonText((string) ($row['meta_json'] ?? ''));
                $meta = is_array($meta) ? $meta : [];

                return [
                        'id' => (int) ($row['id'] ?? 0),
                        'memory_type' => (string) ($row['memory_type'] ?? ''),
                        'memory_key' => (string) ($row['memory_key'] ?? ''),
                        'memory_subtype' => (string) ($row['memory_subtype'] ?? ''),
                        'status' => (string) ($row['status'] ?? ''),
                        'title' => (string) ($row['title'] ?? ''),
                        'summary' => (string) ($row['summary'] ?? ''),
                        'tags' => $tags,
                        'entity_refs' => $entityRefs,
                        'tags_text' => implode(', ', $tags),
                        'entity_refs_text' => implode(', ', $entityRefs),
                        'always_inject' => $this->toBool($meta['always_inject'] ?? false),
                        'inject_group' => (string) ($meta['inject_group'] ?? ''),
                        'source' => (string) ($row['source'] ?? ''),
                        'scope' => (string) ($row['scope'] ?? ''),
                        'scope_ref' => (string) ($row['scope_ref'] ?? ''),
                        'ident' => (string) ($row['ident'] ?? ''),
                        'userid' => (string) ($row['userid'] ?? ''),
                        'session' => (string) ($row['session'] ?? ''),
                        'is_locked' => ((int) ($row['is_locked'] ?? 0)) === 1,
                        'is_mutable_by_llm' => ((int) ($row['is_mutable_by_llm'] ?? 0)) === 1,
                        'is_deletable_by_llm' => ((int) ($row['is_deletable_by_llm'] ?? 0)) === 1,
                        'is_deleted' => ((int) ($row['is_deleted'] ?? 0)) === 1,
                        'is_expired' => ((int) ($row['is_expired'] ?? 0)) === 1,
                        'priority' => (int) ($row['priority'] ?? 0),
                        'confidence' => $row['confidence'] !== null ? (float) $row['confidence'] : null,
                        'valid_from' => (string) ($row['valid_from'] ?? ''),
                        'valid_to' => (string) ($row['valid_to'] ?? ''),
                        'expires_at' => (string) ($row['expires_at'] ?? ''),
                        'last_accessed_at' => (string) ($row['last_accessed_at'] ?? ''),
                        'created_by' => (string) ($row['created_by'] ?? ''),
                        'updated_by' => (string) ($row['updated_by'] ?? ''),
                        'created_at' => (string) ($row['created_at'] ?? ''),
                        'updated_at' => (string) ($row['updated_at'] ?? ''),
                ];
        }

        /**
         * @return array<string, mixed>
         */
        private function buildDetailResponse(int $id): array {
                $row = $this->loadEntryRow($id, true);

                if(!$row) {
                        return [
                                'mode' => 'detail',
                                'found' => false,
                                'detail' => null,
                        ];
                }

                $tags = $this->decodeJsonArray((string) ($row['tags_json'] ?? ''));
                $entityRefs = $this->decodeJsonArray((string) ($row['entity_refs_json'] ?? ''));
                $metaJson = $this->formatJsonText((string) ($row['meta_json'] ?? ''));
                $confidence = $row['confidence'] !== null ? (string) ((float) $row['confidence']) : '';

                return [
                        'mode' => 'detail',
                        'found' => true,
                        'detail' => [
                                'id' => (int) ($row['id'] ?? 0),
                                'headline' => (string) ($row['title'] ?? ''),
                                'summary' => (string) ($row['summary'] ?? ''),
                                'content' => (string) ($row['content'] ?? ''),
                                'tags' => $tags,
                                'entity_refs' => $entityRefs,
                                'badges' => array_values(array_filter([
                                        'Type: ' . (string) ($row['memory_type'] ?? ''),
                                        'Status: ' . (string) ($row['status'] ?? ''),
                                        'Scope: ' . (string) ($row['scope'] ?? ''),
                                        ((int) ($row['is_locked'] ?? 0)) === 1 ? 'Locked' : null,
                                        ((int) ($row['is_deleted'] ?? 0)) === 1 ? 'Deleted' : null,
                                ])),
                                'sections' => [
                                        [
                                                'label' => 'ID',
                                                'value' => (string) ((int) ($row['id'] ?? 0)),
                                        ],
                                        [
                                                'label' => 'Memory type',
                                                'value' => $this->emptyToDash((string) ($row['memory_type'] ?? '')),
                                        ],
                                        [
                                                'label' => 'Memory subtype',
                                                'value' => $this->emptyToDash((string) ($row['memory_subtype'] ?? '')),
                                        ],
                                        [
                                                'label' => 'Memory key',
                                                'value' => $this->emptyToDash((string) ($row['memory_key'] ?? '')),
                                        ],
                                        [
                                                'label' => 'Status',
                                                'value' => $this->emptyToDash((string) ($row['status'] ?? '')),
                                        ],
                                        [
                                                'label' => 'Source',
                                                'value' => $this->emptyToDash((string) ($row['source'] ?? '')),
                                        ],
                                        [
                                                'label' => 'Scope',
                                                'value' => $this->emptyToDash((string) ($row['scope'] ?? '')),
                                        ],
                                        [
                                                'label' => 'Scope ref',
                                                'value' => $this->emptyToDash((string) ($row['scope_ref'] ?? '')),
                                        ],
                                        [
                                                'label' => 'Ident',
                                                'value' => $this->emptyToDash((string) ($row['ident'] ?? '')),
                                        ],
                                        [
                                                'label' => 'User ID',
                                                'value' => $this->emptyToDash((string) ($row['userid'] ?? '')),
                                        ],
                                        [
                                                'label' => 'Session',
                                                'value' => $this->emptyToDash((string) ($row['session'] ?? '')),
                                        ],
                                        [
                                                'label' => 'Priority',
                                                'value' => (string) ((int) ($row['priority'] ?? 0)),
                                        ],
                                        [
                                                'label' => 'Confidence',
                                                'value' => $this->emptyToDash($confidence),
                                        ],
                                        [
                                                'label' => 'Locked',
                                                'value' => ((int) ($row['is_locked'] ?? 0)) === 1 ? 'yes' : 'no',
                                        ],
                                        [
                                                'label' => 'Mutable by LLM',
                                                'value' => ((int) ($row['is_mutable_by_llm'] ?? 0)) === 1 ? 'yes' : 'no',
                                        ],
                                        [
                                                'label' => 'Deletable by LLM',
                                                'value' => ((int) ($row['is_deletable_by_llm'] ?? 0)) === 1 ? 'yes' : 'no',
                                        ],
                                        [
                                                'label' => 'Deleted',
                                                'value' => ((int) ($row['is_deleted'] ?? 0)) === 1 ? 'yes' : 'no',
                                        ],
                                        [
                                                'label' => 'Meta JSON',
                                                'value' => $metaJson,
                                        ],
                                ],
                                'activity' => [
                                        [
                                                'label' => 'Valid from',
                                                'value' => $this->emptyToDash((string) ($row['valid_from'] ?? '')),
                                        ],
                                        [
                                                'label' => 'Valid to',
                                                'value' => $this->emptyToDash((string) ($row['valid_to'] ?? '')),
                                        ],
                                        [
                                                'label' => 'Expires at',
                                                'value' => $this->emptyToDash((string) ($row['expires_at'] ?? '')),
                                        ],
                                        [
                                                'label' => 'Last accessed at',
                                                'value' => $this->emptyToDash((string) ($row['last_accessed_at'] ?? '')),
                                        ],
                                        [
                                                'label' => 'Created by',
                                                'value' => $this->emptyToDash((string) ($row['created_by'] ?? '')),
                                        ],
                                        [
                                                'label' => 'Updated by',
                                                'value' => $this->emptyToDash((string) ($row['updated_by'] ?? '')),
                                        ],
                                        [
                                                'label' => 'Created at',
                                                'value' => $this->emptyToDash((string) ($row['created_at'] ?? '')),
                                        ],
                                        [
                                                'label' => 'Updated at',
                                                'value' => $this->emptyToDash((string) ($row['updated_at'] ?? '')),
                                        ],
                                ],
                        ],
                ];
        }

        /**
         * @return array<string, mixed>
         */
        private function buildRecordResponse(int $id): array {
                $row = $this->loadEntryRow($id, true);

                if(!$row) {
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
         * @return array<string, mixed>
         */
        private function buildContentUpdateResponse(int $id, string $content): array {
                $row = $this->loadEntryRow($id, false);

                if(!$row) {
                        return [
                                'mode' => 'update_content',
                                'ok' => false,
                                'error' => 'Knowledge entry not found',
                                'id' => $id,
                        ];
                }

                $query =
                        'UPDATE `' . self::TABLE_NAME . '` SET ' .
                        '`content` = \'' . $this->database->escape($content) . '\', ' .
                        '`updated_by` = \'' . $this->database->escape(self::UPDATED_BY) . '\', ' .
                        '`updated_at` = NOW() ' .
                        'WHERE `id` = ' . $id . ' LIMIT 1';

                try {
                        $this->database->multiQuery($query);
                }
                catch(\Throwable $e) {
                        return [
                                'mode' => 'update_content',
                                'ok' => false,
                                'error' => 'Failed to update knowledge entry content',
                                'details' => $e->getMessage(),
                                'id' => $id,
                        ];
                }

                $updatedRow = $this->loadEntryRow($id, true);

                return [
                        'mode' => 'update_content',
                        'ok' => true,
                        'action' => 'content updated',
                        'id' => $id,
                        'content' => is_array($updatedRow) ? (string) ($updatedRow['content'] ?? '') : $content,
                        'record' => is_array($updatedRow) ? $this->normalizeRecord($updatedRow) : null,
                ];
        }

        private function buildActionResponse(string $mode, int $id): array {
                $row = $this->loadEntryRow($id, false);

                if(!$row) {
                        return [
                                'mode' => $mode,
                                'ok' => false,
                                'error' => 'Knowledge entry not found',
                                'id' => $id,
                        ];
                }

                $updates = [];
                $actionLabel = $mode;

                if($mode === 'soft_delete') {
                        $updates[] = '`is_deleted` = 1';
                        $actionLabel = 'soft deleted';
                }
                else if($mode === 'restore') {
                        $updates[] = '`is_deleted` = 0';
                        $actionLabel = 'restored';
                }
                else if($mode === 'lock') {
                        $updates[] = '`is_locked` = 1';
                        $actionLabel = 'locked';
                }
                else if($mode === 'unlock') {
                        $updates[] = '`is_locked` = 0';
                        $actionLabel = 'unlocked';
                }
                else if($mode === 'toggle_mutable') {
                        $updates[] = '`is_mutable_by_llm` = ' . (((int) ($row['is_mutable_by_llm'] ?? 0)) === 1 ? '0' : '1');
                        $actionLabel = 'toggled mutable flag';
                }
                else if($mode === 'toggle_deletable') {
                        $updates[] = '`is_deletable_by_llm` = ' . (((int) ($row['is_deletable_by_llm'] ?? 0)) === 1 ? '0' : '1');
                        $actionLabel = 'toggled deletable flag';
                }

                if(!$updates) {
                        return [
                                'mode' => $mode,
                                'ok' => false,
                                'error' => 'Unsupported action',
                                'id' => $id,
                        ];
                }

                $updates[] = '`updated_by` = \'' . $this->database->escape(self::UPDATED_BY) . '\'';
                $updates[] = '`updated_at` = NOW()';

                $query =
                        'UPDATE `' . self::TABLE_NAME . '` SET ' .
                        implode(', ', $updates) .
                        ' WHERE `id` = ' . $id . ' LIMIT 1';

                try {
                        $this->database->multiQuery($query);
                }
                catch(\Throwable $e) {
                        return [
                                'mode' => $mode,
                                'ok' => false,
                                'error' => 'Failed to update knowledge entry',
                                'details' => $e->getMessage(),
                                'id' => $id,
                        ];
                }

                return [
                        'mode' => $mode,
                        'ok' => true,
                        'action' => $actionLabel,
                        'id' => $id,
                        'record' => $this->loadEntryRow($id, true),
                ];
        }

        /**
         * @param array<string, mixed> $row
         * @return array<string, mixed>
         */
        private function normalizeRecord(array $row): array {
                $tagsJson = (string) ($row['tags_json'] ?? '');
                $entityRefsJson = (string) ($row['entity_refs_json'] ?? '');
                $metaJson = (string) ($row['meta_json'] ?? '');

                return [
                        'id' => (int) ($row['id'] ?? 0),
                        'memory_type' => (string) ($row['memory_type'] ?? ''),
                        'memory_key' => (string) ($row['memory_key'] ?? ''),
                        'memory_subtype' => (string) ($row['memory_subtype'] ?? ''),
                        'status' => (string) ($row['status'] ?? ''),
                        'title' => (string) ($row['title'] ?? ''),
                        'content' => (string) ($row['content'] ?? ''),
                        'summary' => (string) ($row['summary'] ?? ''),
                        'tags' => $this->decodeJsonText($tagsJson),
                        'tags_json' => $tagsJson,
                        'entity_refs' => $this->decodeJsonText($entityRefsJson),
                        'entity_refs_json' => $entityRefsJson,
                        'meta' => $this->decodeJsonText($metaJson),
                        'meta_json' => $metaJson,
                        'source' => (string) ($row['source'] ?? ''),
                        'scope' => (string) ($row['scope'] ?? ''),
                        'scope_ref' => (string) ($row['scope_ref'] ?? ''),
                        'ident' => (string) ($row['ident'] ?? ''),
                        'userid' => (string) ($row['userid'] ?? ''),
                        'session' => (string) ($row['session'] ?? ''),
                        'is_locked' => ((int) ($row['is_locked'] ?? 0)) === 1,
                        'is_mutable_by_llm' => ((int) ($row['is_mutable_by_llm'] ?? 0)) === 1,
                        'is_deletable_by_llm' => ((int) ($row['is_deletable_by_llm'] ?? 0)) === 1,
                        'is_deleted' => ((int) ($row['is_deleted'] ?? 0)) === 1,
                        'priority' => (int) ($row['priority'] ?? 0),
                        'confidence' => $row['confidence'] !== null ? (float) $row['confidence'] : null,
                        'valid_from' => (string) ($row['valid_from'] ?? ''),
                        'valid_to' => (string) ($row['valid_to'] ?? ''),
                        'expires_at' => (string) ($row['expires_at'] ?? ''),
                        'last_accessed_at' => (string) ($row['last_accessed_at'] ?? ''),
                        'created_by' => (string) ($row['created_by'] ?? ''),
                        'updated_by' => (string) ($row['updated_by'] ?? ''),
                        'created_at' => (string) ($row['created_at'] ?? ''),
                        'updated_at' => (string) ($row['updated_at'] ?? ''),
                ];
        }

        /**
         * @return array<string, mixed>|null
         */
        private function loadEntryRow(int $id, bool $withContent): ?array {
                if($id <= 0) {
                        return null;
                }

                $this->database->connect();

                $select = $this->buildSelectList();

                if($withContent) {
                        $select[] = 't.`content`';
                }

                $query =
                        'SELECT ' . implode(', ', $select) . ' ' .
                        'FROM `' . self::TABLE_NAME . '` t ' .
                        'WHERE t.`id` = ' . $id . ' ' .
                        'LIMIT 1';

                $row = $this->database->singleQuery($query);

                if(!is_array($row) || count($row) === 0) {
                        return null;
                }

                return $row;
        }

        /**
         * @return array<int, array<string, string>>
         */
        private function getMemoryTypeOptions(): array {
                return [
                        [
                                'value' => '',
                                'label' => 'All types',
                        ],
                        [
                                'value' => 'task',
                                'label' => 'Task',
                        ],
                        [
                                'value' => 'episodic',
                                'label' => 'Episodic',
                        ],
                        [
                                'value' => 'semantic',
                                'label' => 'Semantic',
                        ],
                        [
                                'value' => 'procedural',
                                'label' => 'Procedural',
                        ],
                ];
        }

        /**
         * @return array<int, array<string, string>>
         */
        private function getDistinctOptions(string $column, string $emptyLabel): array {
                $allowedColumns = ['status', 'source', 'scope'];

                if(!in_array($column, $allowedColumns, true)) {
                        return [
                                [
                                        'value' => '',
                                        'label' => $emptyLabel,
                                ]
                        ];
                }

                $this->database->connect();

                $rows = $this->database->multiQuery(
                        'SELECT DISTINCT t.`' . $column . '` AS `value` FROM `' . self::TABLE_NAME . '` t WHERE COALESCE(t.`' . $column . '`, \'\') <> \'\' ORDER BY t.`' . $column . '` ASC'
                );

                $options = [
                        [
                                'value' => '',
                                'label' => $emptyLabel,
                        ]
                ];

                foreach($rows as $row) {
                        if(!is_array($row)) {
                                continue;
                        }

                        $value = trim((string) ($row['value'] ?? ''));
                        if($value === '') {
                                continue;
                        }

                        $options[] = [
                                'value' => $value,
                                'label' => $value,
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

        /**
         * @return array<int, string>
         */
        private function decodeJsonArray(string $json): array {
                $decoded = $this->decodeJsonText($json);

                if(!is_array($decoded)) {
                        return [];
                }

                $out = [];

                foreach($decoded as $item) {
                        if(is_scalar($item)) {
                                $value = trim((string) $item);

                                if($value !== '') {
                                        $out[] = $value;
                                }
                        }
                }

                return array_values(array_unique($out));
        }

        private function emptyToDash(string $value): string {
                return trim($value) !== '' ? $value : '-';
        }

        private function toBool(mixed $value): bool {
                if(is_bool($value)) {
                        return $value;
                }

                if(is_int($value)) {
                        return $value !== 0;
                }

                $s = strtolower(trim((string) $value));
                return in_array($s, ['1', 'true', 'yes', 'on'], true);
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
