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
use Base3\Api\IBase;
use Base3\Api\IClassMap;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Api\ISchemaProvider;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Settings\Api\ISettingsStore;
use AssistantFoundation\Api\IAgentMemory;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentTool;
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

	/**
	 * @var array<int,array<string,mixed>>|null
	 */
	private ?array $resourceOptionsCache = null;

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

		$resourceOptions = $this->listResourceOptions();

		$this->view->assign('settings_group', self::SETTINGS_GROUP);
		$this->view->assign('resource_options', $resourceOptions);
		$this->view->assign('preset_options', $this->listPresetOptions($resourceOptions));
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
				'display_interfaces' => $row['display_interfaces'],
				'interface_text' => $row['interface_text'],
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
		$capabilities = $this->deriveCapabilitiesForType($type);

		if($capabilities === []) {
			$capabilities = $this->getStoredCapabilitiesForSaveFallback($oldId, $id, $payload);
		}

		if($id === '') {
			return $this->buildErrorResponse('Preset id must not be empty.', 'save');
		}

		if($type === '') {
			return $this->buildErrorResponse('Resource type must not be empty.', 'save');
		}

		if($label === '') {
			$label = $id;
		}

		try {
			$config = $this->decodeJsonObject((string)($payload['config_json'] ?? ''), 'Config JSON');
			$docks = $this->decodeJsonObject((string)($payload['docks_json'] ?? ''), 'Docks JSON');
			$meta = $this->decodeJsonObject((string)($payload['meta_json'] ?? ''), 'Meta JSON');
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
		$storedCapabilities = $this->normalizeStringArray($settings['capabilities'] ?? []);
		$derivedCapabilities = $this->deriveCapabilitiesForType($type);
		$capabilities = $derivedCapabilities !== [] ? $derivedCapabilities : $storedCapabilities;
		$displayInterfaces = $this->deriveDisplayInterfacesForType($type);
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
			'display_interfaces' => $displayInterfaces,
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
			'display_interfaces' => $displayInterfaces,
			'interface_text' => implode(', ', $displayInterfaces),
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
			'config_json' => $this->encodePrettyJsonObject($config),
			'docks_json' => $this->encodePrettyJsonObject($docks),
			'meta_json' => $this->encodePrettyJsonObject($meta),
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
		if($this->resourceOptionsCache !== null) {
			return $this->resourceOptionsCache;
		}

		$options = [];

		try {
			if(method_exists($this->classMap, 'getInstancesByInterface')) {
				$resources = $this->classMap->getInstancesByInterface(IAgentResource::class);
			}
			else {
				$resources = $this->classMap->getInstances(['interface' => IAgentResource::class]);
			}
		}
		catch(Throwable) {
			$this->resourceOptionsCache = [];

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

			$options[$id] = $this->normalizeResourceOption($id, $resource);
		}

		ksort($options);
		$this->resourceOptionsCache = array_values($options);

		return $this->resourceOptionsCache;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function listResourceOptionsById(): array {
		$map = [];

		foreach($this->listResourceOptions() as $option) {
			$id = (string)($option['id'] ?? '');

			if($id !== '') {
				$map[$id] = $option;
			}
		}

		return $map;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function normalizeResourceOption(string $id, IAgentResource $resource): array {
		$interfaces = $this->normalizeInterfaceArray(array_values(class_implements($resource) ?: []));
		$displayInterfaces = $this->normalizeDisplayInterfaceArray($interfaces);
		$class = $resource::class;
		$capabilities = [];

		if($resource instanceof IAgentMemory) {
			$capabilities[] = 'memory';
		}

		if($resource instanceof IAgentTool) {
			$capabilities[] = 'tool';
		}

		$capabilities = array_values(array_unique($capabilities));
		sort($capabilities);

		return [
			'id' => $id,
			'class' => $class,
			'description' => $this->safeResourceDescription($resource),
			'capabilities' => $capabilities,
			'capability_text' => implode(', ', $capabilities),
			'display_interfaces' => $displayInterfaces,
			'interface_text' => implode(', ', $displayInterfaces),
			'interfaces' => $interfaces,
			'schema' => $this->safeResourceSchema($resource),
			'docks' => $this->safeResourceDocks($resource)
		];
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function listPresetOptions(array $resourceOptions): array {
		$resourcesById = [];

		foreach($resourceOptions as $resourceOption) {
			$type = (string)($resourceOption['id'] ?? '');

			if($type !== '') {
				$resourcesById[$type] = $resourceOption;
			}
		}

		$rows = [];

		foreach($this->loadRows() as $row) {
			$type = (string)($row['type'] ?? '');
			$resourceOption = $resourcesById[$type] ?? [];
			$interfaces = is_array($resourceOption['interfaces'] ?? null) ? $resourceOption['interfaces'] : [];
			$capabilities = is_array($resourceOption['capabilities'] ?? null) && $resourceOption['capabilities'] !== []
				? $resourceOption['capabilities']
				: (is_array($row['capabilities'] ?? null) ? $row['capabilities'] : []);
			$displayInterfaces = is_array($resourceOption['display_interfaces'] ?? null) ? $resourceOption['display_interfaces'] : [];

			$rows[] = [
				'id' => (string)($row['preset_id'] ?? $row['id'] ?? ''),
				'label' => (string)($row['label'] ?? ''),
				'type' => $type,
				'enabled' => $this->toBool($row['enabled'] ?? true),
				'capabilities' => array_values($capabilities),
				'capability_text' => implode(', ', array_values($capabilities)),
				'interfaces' => array_values($interfaces),
				'display_interfaces' => array_values($displayInterfaces),
				'interface_text' => implode(', ', array_values($displayInterfaces)),
				'class' => (string)($resourceOption['class'] ?? '')
			];
		}

		usort($rows, function(array $left, array $right): int {
			$result = strcmp($this->toLower((string)($left['label'] ?? '')), $this->toLower((string)($right['label'] ?? '')));

			if($result !== 0) {
				return $result;
			}

			return strcmp($this->toLower((string)($left['id'] ?? '')), $this->toLower((string)($right['id'] ?? '')));
		});

		return $rows;
	}

	private function safeResourceDescription(IAgentResource $resource): string {
		try {
			return trim((string)$resource->getDescription());
		}
		catch(Throwable) {
			return '';
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private function safeResourceSchema(IAgentResource $resource): array {
		if(!$resource instanceof ISchemaProvider) {
			return [];
		}

		try {
			$schema = $resource->getSchema();
		}
		catch(Throwable) {
			return [];
		}

		return is_array($schema) ? $schema : [];
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function safeResourceDocks(IAgentResource $resource): array {
		try {
			$docks = $resource->getDockDefinitions();
		}
		catch(Throwable) {
			return [];
		}

		$result = [];

		foreach($docks as $dock) {
			if(is_object($dock) && method_exists($dock, 'toArray')) {
				$value = $dock->toArray();
			}
			elseif(is_array($dock)) {
				$value = $dock;
			}
			else {
				$value = [
					'name' => (string)($dock->name ?? ''),
					'description' => (string)($dock->description ?? ''),
					'interface' => (string)($dock->interface ?? ''),
					'maxConnections' => $dock->maxConnections ?? null,
					'required' => (bool)($dock->required ?? false)
				];
			}

			$name = trim((string)($value['name'] ?? ''));

			if($name === '') {
				continue;
			}

			$result[] = [
				'name' => $name,
				'description' => trim((string)($value['description'] ?? '')),
				'interface' => trim((string)($value['interface'] ?? '')),
				'maxConnections' => isset($value['maxConnections']) ? (int)$value['maxConnections'] : null,
				'required' => $this->toBool($value['required'] ?? false)
			];
		}

		return $result;
	}


	/**
	 * @return array<int,string>
	 */
	private function normalizeInterfaceArray(mixed $value): array {
		if(!is_array($value)) {
			return [];
		}

		$result = [];

		foreach($value as $item) {
			if(!is_scalar($item) && $item !== null) {
				continue;
			}

			$item = trim((string)$item);

			if($item === '') {
				continue;
			}

			$result[] = $item;
		}

		$result = array_values(array_unique($result));
		sort($result);

		return $result;
	}

	/**
	 * @param array<int,string> $interfaces
	 * @return array<int,string>
	 */
	private function normalizeDisplayInterfaceArray(array $interfaces): array {
		$excludedInterfaces = [
			IBase::class,
			IAgentResource::class,
			IAgentMemory::class,
			IAgentTool::class,
			ISchemaProvider::class
		];

		$result = [];

		foreach($interfaces as $interface) {
			$interface = trim((string)$interface);

			if($interface === '' || in_array($interface, $excludedInterfaces, true)) {
				continue;
			}

			$result[] = $this->shortClassName($interface);
		}

		$result = array_values(array_unique($result));
		sort($result);

		return $result;
	}

	private function shortClassName(string $className): string {
		$className = trim($className, '\\');

		if($className === '') {
			return '';
		}

		$pos = strrpos($className, '\\');

		return $pos === false ? $className : substr($className, $pos + 1);
	}

	/**
	 * @return array<int,string>
	 */
	private function deriveDisplayInterfacesForType(string $type): array {
		$type = $this->normalizeTechnicalKey($type);
		$options = $this->listResourceOptionsById();
		$interfaces = $options[$type]['display_interfaces'] ?? [];

		return $this->normalizeStringArray($interfaces);
	}

	/**
	 * @return array<int,string>
	 */
	private function deriveCapabilitiesForType(string $type): array {
		$type = $this->normalizeTechnicalKey($type);
		$options = $this->listResourceOptionsById();
		$capabilities = $options[$type]['capabilities'] ?? [];

		return $this->normalizeStringArray($capabilities);
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<int,string>
	 */
	private function getStoredCapabilitiesForSaveFallback(string $oldId, string $id, array $payload): array {
		foreach([$oldId, $id] as $candidateId) {
			$candidateId = $this->normalizeTechnicalKey($candidateId);

			if($candidateId === '' || !$this->settingsStore->has(self::SETTINGS_GROUP, $candidateId)) {
				continue;
			}

			$settings = $this->settingsStore->get(self::SETTINGS_GROUP, $candidateId, []);

			if(is_array($settings)) {
				$capabilities = $this->normalizeStringArray($settings['capabilities'] ?? []);

				if($capabilities !== []) {
					return $capabilities;
				}
			}
		}

		return $this->normalizeStringArray($payload['capabilities'] ?? []);
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

	/**
	 * Encodes associative setting payloads as JSON objects for the editor contract.
	 * Empty PHP arrays would otherwise become [], which breaks object-based editor controls.
	 */
	private function encodePrettyJsonObject(mixed $value): string {
		if(is_array($value) && $value === []) {
			return '{}';
		}

		return $this->encodePrettyJson($value);
	}

	private function toLower(string $value): string {
		if(function_exists('mb_strtolower')) {
			return mb_strtolower($value);
		}

		return strtolower($value);
	}
}
