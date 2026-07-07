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
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Settings\Api\ISettingsStore;
use Throwable;

/**
 * ToolProfileAdminDisplay
 *
 * Administrates simple tool profiles stored in ISettingsStore.
 * A tool profile only references already configured agent component presets.
 */
final class ToolProfileAdminDisplay implements IDisplay {

	private const SETTINGS_GROUP = 'tool-profile';
	private const TOOL_PRESET_SETTINGS_GROUP = 'agent-component-preset';
	private const BATCH_SIZE = 50;

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly IAssetResolver $assetResolver,
		private readonly ISettingsStore $settingsStore,
		private readonly ILinkTargetService $linkTargetService
	) {}

	public static function getName(): string {
		return 'toolprofileadmindisplay';
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
		return 'Administrates MissionBay tool profiles.';
	}

	private function handleHtml(): string {
		$this->view->setPath(DIR_PLUGIN . 'MissionBay');
		$this->view->setTemplate('Display/ToolProfileAdminDisplay.php');

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
		$this->view->assign('tool_preset_options', $this->listToolPresetOptions());
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
				'error' => 'Tool profile admin request failed.',
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
				'profile_id' => $row['profile_id'],
				'label' => $row['label'],
				'type' => $row['type'],
				'enabled' => $row['enabled'],
				'enabled_label' => $row['enabled_label'],
				'tools' => $row['tools'],
				'token_configured' => $row['token_configured'],
				'token_configured_label' => $row['token_configured_label'],
				'tool_count' => $row['tool_count'],
				'tool_text' => $row['tool_text']
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
			return $this->buildErrorResponse('Missing profile id.', 'record');
		}

		$id = $this->normalizeTechnicalKey($id);

		if(!$this->settingsStore->has(self::SETTINGS_GROUP, $id)) {
			return $this->buildErrorResponse('Profile not found: ' . $id, 'record');
		}

		$settings = $this->settingsStore->get(self::SETTINGS_GROUP, $id, []);

		if(!is_array($settings)) {
			$settings = [];
		}

		$row = $this->normalizeRow($id, $settings);

		return [
			'ok' => true,
			'mode' => 'record',
			'record' => $row,
			'tool_preset_options' => $this->listToolPresetOptions()
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
		$type = $this->normalizeTechnicalKey((string)($payload['type'] ?? 'mcp'));
		$enabled = $this->toBool($payload['enabled'] ?? true);
		$token = trim((string)($payload['token'] ?? ''));
		$tools = $this->normalizeToolList($payload['tools'] ?? []);

		if($id === '') {
			return $this->buildErrorResponse('Profile id must not be empty.', 'save');
		}

		if($type === '') {
			$type = 'mcp';
		}

		if($label === '') {
			$label = $id;
		}

		$toolOptions = $this->listToolPresetOptionsById();

		foreach($tools as $toolId) {
			if(!isset($toolOptions[$toolId])) {
				return $this->buildErrorResponse('Selected tool preset is not available: ' . $toolId, 'save');
			}
		}

		$isRename = $oldId !== '' && $oldId !== $id;

		if($isRename && $this->settingsStore->has(self::SETTINGS_GROUP, $id)) {
			return $this->buildErrorResponse('Target profile already exists: ' . $id, 'save');
		}

		$profile = [
			'id' => $id,
			'label' => $label,
			'type' => $type,
			'enabled' => $enabled,
			'token' => $token,
			'tools' => $tools
		];

		try {
			$this->settingsStore->set(self::SETTINGS_GROUP, $id, $profile);

			if($isRename) {
				$this->settingsStore->remove(self::SETTINGS_GROUP, $oldId);
			}

			$this->settingsStore->save();
		}
		catch(Throwable $e) {
			return $this->buildErrorResponse('Profile could not be saved: ' . $e->getMessage(), 'save');
		}

		return [
			'ok' => true,
			'mode' => 'save',
			'action' => $isRename ? 'renamed and saved' : 'saved',
			'record' => $this->normalizeRow($id, $profile)
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function buildDeleteResponse(string $id): array {
		$id = $this->normalizeTechnicalKey($id);

		if($id === '') {
			return $this->buildErrorResponse('Missing profile id.', 'delete');
		}

		if(!$this->settingsStore->has(self::SETTINGS_GROUP, $id)) {
			return $this->buildErrorResponse('Profile not found: ' . $id, 'delete');
		}

		try {
			$this->settingsStore->remove(self::SETTINGS_GROUP, $id);
			$this->settingsStore->save();
		}
		catch(Throwable $e) {
			return $this->buildErrorResponse('Profile could not be deleted: ' . $e->getMessage(), 'delete');
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
			return $this->buildErrorResponse('Profile store could not be reloaded: ' . $e->getMessage(), 'reload');
		}

		return [
			'ok' => true,
			'mode' => 'reload',
			'action' => 'reloaded',
			'tool_preset_options' => $this->listToolPresetOptions()
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
		$type = $this->normalizeTechnicalKey((string)($settings['type'] ?? 'mcp'));
		$enabled = $this->toBool($settings['enabled'] ?? true);
		$token = trim((string)($settings['token'] ?? ''));
		$tools = $this->normalizeToolList($settings['tools'] ?? []);
		$toolText = $this->buildToolText($tools);

		if($label === '') {
			$label = $id;
		}

		if($type === '') {
			$type = 'mcp';
		}

		$profile = [
			'id' => $id,
			'label' => $label,
			'type' => $type,
			'enabled' => $enabled,
			'token' => $token,
			'tools' => $tools
		];

		return [
			'id' => $id,
			'profile_id' => $id,
			'old_id' => $id,
			'label' => $label,
			'type' => $type,
			'enabled' => $enabled,
			'enabled_label' => $enabled ? 'enabled' : 'disabled',
			'token' => $token,
			'token_configured' => $token !== '',
			'token_configured_label' => $token !== '' ? 'configured' : 'missing',
			'tools' => $tools,
			'tool_count' => count($tools),
			'tool_text' => $toolText,
			'profile_json' => $this->encodePrettyJson($profile),
			'profile' => $profile
		];
	}

	/**
	 * @param mixed $sortPayload
	 * @return array<string,string>
	 */
	private function normalizeSort(mixed $sortPayload): array {
		$allowedKeys = [
			'profile_id',
			'label',
			'type',
			'enabled_label',
			'token_configured_label',
			'tool_count',
			'tool_text'
		];

		$sort = [
			'key' => 'profile_id',
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

		$key = isset($first['key']) ? (string)$first['key'] : 'profile_id';
		if(!in_array($key, $allowedKeys, true)) {
			$key = 'profile_id';
		}

		$dir = isset($first['dir']) ? strtolower((string)$first['dir']) : 'asc';
		$dir = $dir === 'desc' ? 'desc' : 'asc';
		$type = $key === 'tool_count' ? 'int' : 'string';

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

		foreach(['enabled', 'type'] as $key) {
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
				(string)($row['profile_id'] ?? ''),
				(string)($row['label'] ?? ''),
				(string)($row['type'] ?? ''),
				(string)($row['enabled_label'] ?? ''),
				(string)($row['token_configured_label'] ?? ''),
				(string)($row['tool_text'] ?? ''),
				(string)($row['profile_json'] ?? '')
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

		if(isset($filters['type'])) {
			$value = $this->toLower((string)($row['type'] ?? ''));
			$needle = $this->toLower((string)$filters['type']);

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
		$key = $sort['key'] ?? 'profile_id';
		$dir = $sort['dir'] ?? 'asc';

		usort($rows, function(array $left, array $right) use ($key, $dir): int {
			if($key === 'tool_count') {
				$result = ((int)($left[$key] ?? 0)) <=> ((int)($right[$key] ?? 0));
			}
			else {
				$result = strcmp($this->toLower((string)($left[$key] ?? '')), $this->toLower((string)($right[$key] ?? '')));
			}

			if($result === 0) {
				$result = strcmp($this->toLower((string)($left['profile_id'] ?? '')), $this->toLower((string)($right['profile_id'] ?? '')));
			}

			return $dir === 'desc' ? -$result : $result;
		});

		return $rows;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function listToolPresetOptions(): array {
		try {
			$group = $this->settingsStore->getGroup(self::TOOL_PRESET_SETTINGS_GROUP);
		}
		catch(Throwable) {
			return [];
		}

		if(!is_array($group)) {
			return [];
		}

		$options = [];

		foreach($group as $id => $settings) {
			if(!is_string($id) && !is_int($id)) {
				continue;
			}

			if(!is_array($settings)) {
				$settings = [];
			}

			$optionId = $this->normalizeTechnicalKey((string)($settings['id'] ?? $id));
			$capabilities = $this->normalizeStringArray($settings['capabilities'] ?? []);

			if($optionId === '' || !in_array('tool', $capabilities, true)) {
				continue;
			}

			$label = trim((string)($settings['label'] ?? ''));
			$type = $this->normalizeTechnicalKey((string)($settings['type'] ?? ''));
			$enabled = $this->toBool($settings['enabled'] ?? true);

			if($label === '') {
				$label = $optionId;
			}

			$options[] = [
				'id' => $optionId,
				'label' => $label,
				'type' => $type,
				'enabled' => $enabled,
				'enabled_label' => $enabled ? 'enabled' : 'disabled'
			];
		}

		usort($options, function(array $left, array $right): int {
			$result = strcmp($this->toLower((string)($left['label'] ?? '')), $this->toLower((string)($right['label'] ?? '')));

			if($result !== 0) {
				return $result;
			}

			return strcmp($this->toLower((string)($left['id'] ?? '')), $this->toLower((string)($right['id'] ?? '')));
		});

		return $options;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function listToolPresetOptionsById(): array {
		$map = [];

		foreach($this->listToolPresetOptions() as $option) {
			$id = (string)($option['id'] ?? '');

			if($id !== '') {
				$map[$id] = $option;
			}
		}

		return $map;
	}

	/**
	 * @param array<int,string> $tools
	 */
	private function buildToolText(array $tools): string {
		if($tools === []) {
			return '';
		}

		$options = $this->listToolPresetOptionsById();
		$labels = [];

		foreach($tools as $toolId) {
			$label = (string)($options[$toolId]['label'] ?? $toolId);

			if($label !== $toolId) {
				$labels[] = $label . ' (' . $toolId . ')';
			}
			else {
				$labels[] = $toolId;
			}
		}

		return implode(', ', $labels);
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
	 * @return array<int,string>
	 */
	private function normalizeToolList(mixed $value): array {
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

			if($item === '' || in_array($item, $result, true)) {
				continue;
			}

			$result[] = $item;
		}

		return $result;
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
