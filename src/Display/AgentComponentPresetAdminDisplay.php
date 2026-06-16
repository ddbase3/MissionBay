<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace MissionBay\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IClassMap;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentResource;
use Throwable;

/**
 * AgentComponentPresetAdminDisplay
 *
 * Provides a first ModularGrid based administration surface for agent
 * component presets stored in ISettingsStore.
 */
final class AgentComponentPresetAdminDisplay implements IDisplay {

        private const SETTINGS_GROUP = 'agent-component-preset';
        private const BATCH_SIZE = 50;

        public function __construct(
                private readonly IRequest $request,
                private readonly IMvcView $view,
                private readonly IAssetResolver $assetResolver,
                private readonly ISettingsStore $settingsStore,
                private readonly ILinkTargetService $linkTargetService,
                private readonly IClassMap $classMap
        ) {}

        public static function getName(): string {
                return 'agentcomponentpresetadmindisplay';
        }

        public function setData($data) {
                // no-op
        }

        public function getOutput(string $out = 'html', bool $final = false): string {
                $out = strtolower((string)$out);

                if($out === 'json') {
                        return $this->handleJson($final);
                }

                return $this->handleHtml();
        }

        public function getHelp(): string {
                return 'Administrates MissionBay agent component presets.';
        }

        private function handleHtml(): string {
                $this->view->setPath(DIR_PLUGIN . 'MissionBay');
                $this->view->setTemplate('Display/AgentComponentPresetAdminDisplay.php');

                $this->view->assign(
                        'service',
                        $this->linkTargetService->getLink(
                                [
                                        'name' => self::getName(),
                                        'out' => 'json'
                                ]
                        )
                );

                $this->view->assign('settings_group', self::SETTINGS_GROUP);
                $this->view->assign('resource_options', $this->listResourceOptions());
                $this->view->assign('resolve', fn($src) => $this->assetResolver->resolve((string)$src));

                return $this->view->loadTemplate();
        }

        private function handleJson(bool $final = false): string {
                try {
                        $response = $this->buildJsonResponse();
                }
                catch(Throwable $e) {
                        $response = [
                                'ok' => false,
                                'mode' => 'error',
                                'error' => 'Preset admin request failed.',
                                'details' => $e->getMessage()
                        ];
                }

                if($final && !headers_sent()) {
                        header('Content-Type: application/json; charset=utf-8');
                }

                return (string)json_encode(
                        $response,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
                );
        }

        /**
         * @return array<string,mixed>
         */
        private function buildJsonResponse(): array {
                $payload = $this->request->getJsonBody();

                if(!is_array($payload)) {
                        $payload = [];
                }

                $request = $this->normalizeRequest($payload);

                if($request['mode'] === 'record') {
                        return $this->buildRecordResponse($request['id']);
                }

                if($request['mode'] === 'save') {
                        return $this->buildSaveResponse($payload);
                }

                if($request['mode'] === 'delete') {
                        return $this->buildDeleteResponse($request['id']);
                }

                if($request['mode'] === 'reload') {
                        return $this->buildReloadResponse();
                }

                return $this->buildPageResponse($request);
        }

        /**
         * @param array<string,mixed> $payload
         * @return array<string,mixed>
         */
        private function normalizeRequest(array $payload): array {
                $mode = 'page';
                $allowedModes = ['page', 'record', 'save', 'delete', 'reload'];

                if(isset($payload['mode']) && is_string($payload['mode']) && in_array($payload['mode'], $allowedModes, true)) {
                        $mode = $payload['mode'];
                }

                $page = isset($payload['page']) ? (int)$payload['page'] : 1;
                $page = max(1, $page);

                $pageSize = isset($payload['pageSize']) ? (int)$payload['pageSize'] : self::BATCH_SIZE;
                $pageSize = max(1, min(250, $pageSize));

                $search = '';
                if(isset($payload['search']) && is_scalar($payload['search'])) {
                        $search = trim((string)$payload['search']);
                }

                $id = '';
                if(isset($payload['id']) && is_scalar($payload['id'])) {
                        $id = trim((string)$payload['id']);
                }

                return [
                        'mode' => $mode,
                        'page' => $page,
                        'pageSize' => $pageSize,
                        'search' => $search,
                        'id' => $id,
                        'sort' => $this->normalizeSort($payload['sort'] ?? null),
                        'filters' => $this->normalizeFilters($payload['filters'] ?? [])
                ];
        }

        /**
         * @param array<string,mixed> $request
         * @return array<string,mixed>
         */
        private function buildPageResponse(array $request): array {
                $rows = $this->loadRows();
                $rows = $this->applySearch($rows, $request['search']);
                $rows = $this->applyFilters($rows, $request['filters']);
                $rows = $this->applySort($rows, $request['sort']);

                $total = count($rows);
                $pageSize = (int)$request['pageSize'];
                $page = (int)$request['page'];
                $totalPages = $pageSize > 0 ? (int)ceil($total / $pageSize) : 0;
                $offset = max(0, ($page - 1) * $pageSize);
                $pageRows = array_slice($rows, $offset, $pageSize);
                $data = [];

                foreach($pageRows as $row) {
                        $data[] = [
                                'id' => $row['id'],
                                'preset_id' => $row['preset_id'],
                                'label' => $row['label'],
                                'type' => $row['type'],
                                'enabled' => $row['enabled'],
                                'enabled_label' => $row['enabled_label'],
                                'capabilities' => $row['capabilities'],
                                'capability_text' => $row['capability_text'],
                                'category' => $row['category'],
                                'status' => $row['status'],
                                'risk' => $row['risk'],
                                'version' => $row['version'],
                                'description' => $row['description'],
                                'config_count' => $row['config_count'],
                                'dock_count' => $row['dock_count']
                        ];
                }

                return [
                        'ok' => true,
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
                        'appliedGroup' => []
                ];
        }

        /**
         * @return array<string,mixed>
         */
        private function buildRecordResponse(string $id): array {
                if($id === '') {
                        return $this->buildErrorResponse('Missing preset id.', 'record');
                }

                if(!$this->settingsStore->has(self::SETTINGS_GROUP, $id)) {
                        return $this->buildErrorResponse('Preset not found: ' . $id, 'record');
                }

                $settings = $this->settingsStore->get(self::SETTINGS_GROUP, $id, []);

                if(!is_array($settings)) {
                        $settings = [];
                }

                $row = $this->normalizeRow($id, $settings);

                return [
                        'ok' => true,
                        'mode' => 'record',
                        'record' => $row
                ];
        }

        /**
         * @param array<string,mixed> $payload
         * @return array<string,mixed>
         */
        private function buildSaveResponse(array $payload): array {
                $oldId = $this->normalizeTechnicalKey((string)($payload['old_id'] ?? ''));
                $id = $this->normalizeTechnicalKey((string)($payload['id'] ?? ''));
                $label = trim((string)($payload['label'] ?? ''));
                $type = $this->normalizeTechnicalKey((string)($payload['type'] ?? ''));
                $enabled = $this->toBool($payload['enabled'] ?? true);
                $capabilities = $this->normalizeStringArray($payload['capabilities'] ?? []);

                if($id === '') {
                        return $this->buildErrorResponse('Preset id must not be empty.', 'save');
                }

                if($type === '') {
                        return $this->buildErrorResponse('Resource type must not be empty.', 'save');
                }

                if($label === '') {
                        $label = $id;
                }

                if($capabilities === []) {
                        return $this->buildErrorResponse('At least one capability is required.', 'save');
                }

                try {
                        $config = $this->decodeJsonObject((string)($payload['config_json'] ?? ''), 'Config JSON');
                        $docks = $this->decodeJsonObject((string)($payload['docks_json'] ?? ''), 'Docks JSON');
                        $meta = $this->decodeJsonObject((string)($payload['meta_json'] ?? ''), 'Meta JSON');
                        $meta = $this->mergeMetaPayload($meta, $payload);
                }
                catch(Throwable $e) {
                        return $this->buildErrorResponse($e->getMessage(), 'save');
                }

                $isRename = $oldId !== '' && $oldId !== $id;

                if($isRename && $this->settingsStore->has(self::SETTINGS_GROUP, $id)) {
                        return $this->buildErrorResponse('Target preset already exists: ' . $id, 'save');
                }

                $preset = [
                        'id' => $id,
                        'label' => $label,
                        'type' => $type,
                        'enabled' => $enabled,
                        'capabilities' => $capabilities,
                        'config' => $config,
                        'docks' => $docks,
                        'meta' => $meta
                ];

                try {
                        $this->settingsStore->set(self::SETTINGS_GROUP, $id, $preset);

                        if($isRename) {
                                $this->settingsStore->remove(self::SETTINGS_GROUP, $oldId);
                        }

                        $this->settingsStore->save();
                }
                catch(Throwable $e) {
                        return $this->buildErrorResponse('Preset could not be saved: ' . $e->getMessage(), 'save');
                }

                return [
                        'ok' => true,
                        'mode' => 'save',
                        'action' => $isRename ? 'renamed and saved' : 'saved',
                        'id' => $id,
                        'record' => $this->normalizeRow($id, $preset)
                ];
        }

        /**
         * @return array<string,mixed>
         */
        private function buildDeleteResponse(string $id): array {
                $id = $this->normalizeTechnicalKey($id);

                if($id === '') {
                        return $this->buildErrorResponse('Missing preset id.', 'delete');
                }

                if(!$this->settingsStore->has(self::SETTINGS_GROUP, $id)) {
                        return $this->buildErrorResponse('Preset not found: ' . $id, 'delete');
                }

                try {
                        $this->settingsStore->remove(self::SETTINGS_GROUP, $id);
                        $this->settingsStore->save();
                }
                catch(Throwable $e) {
                        return $this->buildErrorResponse('Preset could not be deleted: ' . $e->getMessage(), 'delete');
                }

                return [
                        'ok' => true,
                        'mode' => 'delete',
                        'action' => 'deleted',
                        'id' => $id
                ];
        }

        /**
         * @return array<string,mixed>
         */
        private function buildReloadResponse(): array {
                try {
                        $this->settingsStore->reload();
                }
                catch(Throwable $e) {
                        return $this->buildErrorResponse('Preset store could not be reloaded: ' . $e->getMessage(), 'reload');
                }

                return [
                        'ok' => true,
                        'mode' => 'reload',
                        'action' => 'reloaded'
                ];
        }

        /**
         * @return array<int,array<string,mixed>>
         */
        private function loadRows(): array {
                try {
                        $group = $this->settingsStore->getGroup(self::SETTINGS_GROUP);
                }
                catch(Throwable) {
                        return [];
                }

                if(!is_array($group)) {
                        return [];
                }

                $rows = [];

                foreach($group as $id => $settings) {
                        if(!is_string($id) && !is_int($id)) {
                                continue;
                        }

                        if(!is_array($settings)) {
                                $settings = [];
                        }

                        $rows[] = $this->normalizeRow((string)$id, $settings);
                }

                return $rows;
        }

        /**
         * @param array<string,mixed> $settings
         * @return array<string,mixed>
         */
        private function normalizeRow(string $id, array $settings): array {
                $id = $this->normalizeTechnicalKey((string)($settings['id'] ?? $id));
                $label = trim((string)($settings['label'] ?? ''));
                $type = $this->normalizeTechnicalKey((string)($settings['type'] ?? ''));
                $enabled = $this->toBool($settings['enabled'] ?? true);
                $capabilities = $this->normalizeStringArray($settings['capabilities'] ?? []);
                $config = is_array($settings['config'] ?? null) ? $settings['config'] : [];
                $docks = is_array($settings['docks'] ?? null) ? $settings['docks'] : [];
                $meta = is_array($settings['meta'] ?? null) ? $settings['meta'] : [];

                if($label === '') {
                        $label = $id;
                }

                $description = trim((string)($meta['description'] ?? ''));
                $category = trim((string)($meta['category'] ?? ''));
                $status = trim((string)($meta['status'] ?? ''));
                $risk = trim((string)($meta['risk'] ?? ''));
                $version = isset($meta['version']) && is_scalar($meta['version']) ? (string)$meta['version'] : '';

                $preset = [
                        'id' => $id,
                        'label' => $label,
                        'type' => $type,
                        'enabled' => $enabled,
                        'capabilities' => $capabilities,
                        'config' => $config,
                        'docks' => $docks,
                        'meta' => $meta
                ];

                return [
                        'id' => $id,
                        'preset_id' => $id,
                        'old_id' => $id,
                        'label' => $label,
                        'type' => $type,
                        'enabled' => $enabled,
                        'enabled_label' => $enabled ? 'enabled' : 'disabled',
                        'capabilities' => $capabilities,
                        'capability_text' => implode(', ', $capabilities),
                        'category' => $category,
                        'status' => $status,
                        'risk' => $risk,
                        'version' => $version,
                        'description' => $description,
                        'config' => $config,
                        'docks' => $docks,
                        'meta' => $meta,
                        'config_count' => count($config),
                        'dock_count' => count($docks),
                        'capabilities_edit' => implode(', ', $capabilities),
                        'config_json' => $this->encodePrettyJson($config),
                        'docks_json' => $this->encodePrettyJson($docks),
                        'meta_json' => $this->encodePrettyJson($meta),
                        'preset_json' => $this->encodePrettyJson($preset),
                        'preset' => $preset
                ];
        }

        /**
         * @param mixed $sortPayload
         * @return array<string,string>
         */
        private function normalizeSort(mixed $sortPayload): array {
                $allowedKeys = [
                        'preset_id',
                        'label',
                        'type',
                        'enabled_label',
                        'capability_text',
                        'category',
                        'status',
                        'risk',
                        'version',
                        'description',
                        'config_count',
                        'dock_count'
                ];

                $sort = [
                        'key' => 'preset_id',
                        'dir' => 'asc',
                        'type' => 'string'
                ];

                if(!is_array($sortPayload) || count($sortPayload) === 0) {
                        return $sort;
                }

                $first = reset($sortPayload);

                if(!is_array($first)) {
                        return $sort;
                }

                $key = isset($first['key']) ? (string)$first['key'] : 'preset_id';
                if(!in_array($key, $allowedKeys, true)) {
                        $key = 'preset_id';
                }

                $dir = isset($first['dir']) ? strtolower((string)$first['dir']) : 'asc';
                $dir = $dir === 'desc' ? 'desc' : 'asc';
                $type = in_array($key, ['config_count', 'dock_count'], true) ? 'int' : 'string';

                return [
                        'key' => $key,
                        'dir' => $dir,
                        'type' => $type
                ];
        }

        /**
         * @return array<string,mixed>
         */
        private function normalizeFilters(mixed $filters): array {
                if(!is_array($filters)) {
                        return [];
                }

                $out = [];

                foreach(['enabled', 'capability', 'type', 'category', 'status'] as $key) {
                        if(!array_key_exists($key, $filters) || $filters[$key] === null || $filters[$key] === '') {
                                continue;
                        }

                        $out[$key] = trim((string)$filters[$key]);
                }

                return $out;
        }

        /**
         * @param array<int,array<string,mixed>> $rows
         * @return array<int,array<string,mixed>>
         */
        private function applySearch(array $rows, string $search): array {
                if($search === '') {
                        return $rows;
                }

                $needle = $this->toLower($search);
                $result = [];

                foreach($rows as $row) {
                        $haystack = implode("\n", [
                                (string)($row['preset_id'] ?? ''),
                                (string)($row['label'] ?? ''),
                                (string)($row['type'] ?? ''),
                                (string)($row['capability_text'] ?? ''),
                                (string)($row['category'] ?? ''),
                                (string)($row['status'] ?? ''),
                                (string)($row['risk'] ?? ''),
                                (string)($row['description'] ?? ''),
                                (string)($row['preset_json'] ?? '')
                        ]);

                        if(str_contains($this->toLower($haystack), $needle)) {
                                $result[] = $row;
                        }
                }

                return $result;
        }

        /**
         * @param array<int,array<string,mixed>> $rows
         * @param array<string,mixed> $filters
         * @return array<int,array<string,mixed>>
         */
        private function applyFilters(array $rows, array $filters): array {
                if($filters === []) {
                        return $rows;
                }

                $result = [];

                foreach($rows as $row) {
                        if(!$this->rowMatchesFilters($row, $filters)) {
                                continue;
                        }

                        $result[] = $row;
                }

                return $result;
        }

        /**
         * @param array<string,mixed> $row
         * @param array<string,mixed> $filters
         */
        private function rowMatchesFilters(array $row, array $filters): bool {
                if(isset($filters['enabled'])) {
                        $enabled = $this->toBool($row['enabled'] ?? false) ? '1' : '0';

                        if($enabled !== (string)$filters['enabled']) {
                                return false;
                        }
                }

                if(isset($filters['capability']) && !in_array((string)$filters['capability'], $row['capabilities'] ?? [], true)) {
                        return false;
                }

                foreach(['type', 'category', 'status'] as $key) {
                        if(!isset($filters[$key])) {
                                continue;
                        }

                        $value = $this->toLower((string)($row[$key] ?? ''));
                        $needle = $this->toLower((string)$filters[$key]);

                        if($needle !== '' && !str_contains($value, $needle)) {
                                return false;
                        }
                }

                return true;
        }

        /**
         * @param array<int,array<string,mixed>> $rows
         * @param array<string,string> $sort
         * @return array<int,array<string,mixed>>
         */
        private function applySort(array $rows, array $sort): array {
                $key = $sort['key'] ?? 'preset_id';
                $dir = $sort['dir'] ?? 'asc';

                usort($rows, function(array $left, array $right) use ($key, $dir): int {
                        if(in_array($key, ['config_count', 'dock_count'], true)) {
                                $result = ((int)($left[$key] ?? 0)) <=> ((int)($right[$key] ?? 0));
                        }
                        else {
                                $result = strcmp($this->toLower((string)($left[$key] ?? '')), $this->toLower((string)($right[$key] ?? '')));
                        }

                        if($result === 0) {
                                $result = strcmp($this->toLower((string)($left['preset_id'] ?? '')), $this->toLower((string)($right['preset_id'] ?? '')));
                        }

                        return $dir === 'desc' ? -$result : $result;
                });

                return $rows;
        }

        /**
         * @return array<int,array<string,string>>
         */
        private function listResourceOptions(): array {
                $options = [];

                try {
                        $resources = $this->classMap->getInstances(['interface' => IAgentResource::class]);
                }
                catch(Throwable) {
                        return [];
                }

                foreach($resources as $resource) {
                        if(!$resource instanceof IAgentResource) {
                                continue;
                        }

                        $id = $this->normalizeTechnicalKey((string)$resource::getName());

                        if($id === '') {
                                continue;
                        }

                        $options[$id] = [
                                'id' => $id,
                                'class' => $resource::class
                        ];
                }

                ksort($options);

                return array_values($options);
        }

        /**
         * @param array<string,mixed> $meta
         * @param array<string,mixed> $payload
         * @return array<string,mixed>
         */
        private function mergeMetaPayload(array $meta, array $payload): array {
                foreach(['description', 'category', 'risk', 'status'] as $key) {
                        if(!array_key_exists($key, $payload) || (!is_scalar($payload[$key]) && $payload[$key] !== null)) {
                                continue;
                        }

                        $meta[$key] = trim((string)$payload[$key]);
                }

                if(array_key_exists('version', $payload) && (is_scalar($payload['version']) || $payload['version'] === null)) {
                        $version = trim((string)$payload['version']);

                        if($version === '') {
                                unset($meta['version']);
                        }
                        elseif(is_numeric($version)) {
                                $meta['version'] = str_contains($version, '.') ? (float)$version : (int)$version;
                        }
                        else {
                                $meta['version'] = $version;
                        }
                }

                return $meta;
        }

        /**
         * @return array<string,mixed>
         */
        private function buildErrorResponse(string $message, string $mode): array {
                return [
                        'ok' => false,
                        'mode' => $mode,
                        'error' => $message
                ];
        }

        /**
         * @return array<string,mixed>
         */
        private function decodeJsonObject(string $raw, string $label): array {
                $raw = trim($raw);

                if($raw === '') {
                        return [];
                }

                $decoded = json_decode($raw, true);

                if(json_last_error() !== JSON_ERROR_NONE) {
                        throw new \InvalidArgumentException($label . ' must be valid JSON: ' . json_last_error_msg());
                }

                if(!is_array($decoded)) {
                        throw new \InvalidArgumentException($label . ' must decode to a JSON object or array.');
                }

                return $decoded;
        }

        /**
         * @return array<int,string>
         */
        private function normalizeStringArray(mixed $value): array {
                if(is_string($value)) {
                        $value = explode(',', $value);
                }

                if(!is_array($value)) {
                        return [];
                }

                $result = [];

                foreach($value as $item) {
                        if(!is_scalar($item) && $item !== null) {
                                continue;
                        }

                        $item = $this->normalizeTechnicalKey((string)$item);

                        if($item === '') {
                                continue;
                        }

                        $result[] = $item;
                }

                $result = array_values(array_unique($result));
                sort($result);

                return $result;
        }

        private function normalizeTechnicalKey(string $value): string {
                $value = strtolower(trim($value));

                return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
        }

        private function toBool(mixed $value): bool {
                if(is_bool($value)) {
                        return $value;
                }

                if(is_int($value)) {
                        return $value === 1;
                }

                $value = strtolower(trim((string)$value));

                return in_array($value, ['1', 'true', 'yes', 'on'], true);
        }

        private function encodePrettyJson(mixed $value): string {
                $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

                return is_string($json) ? $json : '{}';
        }

        private function toLower(string $value): string {
                if(function_exists('mb_strtolower')) {
                        return mb_strtolower($value);
                }

                return strtolower($value);
        }
}
