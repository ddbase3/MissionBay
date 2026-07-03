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
use Base3\Database\Api\IDatabase;
use Base3\LinkTarget\Api\ILinkTargetService;
use InvalidArgumentException;
use Throwable;

final class UserPrefDefAdminDisplay implements IDisplay {

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly IAssetResolver $assetResolver,
		private readonly IDatabase $database,
		private readonly ILinkTargetService $linkTargetService
	) {}

	public static function getName(): string {
		return 'userprefdefadmindisplay';
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

	public function getHelp(): string {
		return 'Help of UserPrefDefAdminDisplay';
	}

	private function handleHtml(): string {
		$this->view->setPath(DIR_PLUGIN . 'MissionBay');
		$this->view->setTemplate('Display/UserPrefDefAdminDisplay.php');

		$this->view->assign(
			'service',
			$this->linkTargetService->getLink(
				[
					'name' => self::getName(),
					'out' => 'json'
				]
			)
		);

		$this->view->assign(
			'modularGridCssUrl',
			$this->assetResolver->resolve('plugin/ClientStack/assets/modulargrid/styles/modulargrid.css')
		);

		$this->view->assign(
			'modularGridJsUrl',
			$this->assetResolver->resolve('plugin/ClientStack/assets/modulargrid/index.js')
		);

		$this->view->assign(
			'modularDialogCssUrl',
			$this->assetResolver->resolve('plugin/ClientStack/assets/modulardialog/styles/modulardialog.css')
		);

		$this->view->assign(
			'modularDialogJsUrl',
			$this->assetResolver->resolve('plugin/ClientStack/assets/modulardialog/index.js')
		);

		$this->view->assign('valueTypeOptions', $this->getValueTypeOptions(true));
		$this->view->assign('scopeOptions', $this->getScopeOptions(true));
		$this->view->assign('enabledOptions', $this->getEnabledOptions(true));

		return $this->view->loadTemplate();
	}

	private function handleJson(bool $final = false): string {
		try {
			$response = $this->buildJsonResponse();
		}
		catch(Throwable $e) {
			$response = [
				'ok' => false,
				'error' => 'User preference definition request failed.',
				'details' => $e->getMessage(),
			];
		}

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

		if($request['mode'] === 'record') {
			return $this->buildRecordResponse($request['id']);
		}

		if($request['mode'] === 'save') {
			return $this->buildSaveResponse($payload);
		}

		if($request['mode'] === 'delete') {
			return $this->buildDeleteResponse($request['id']);
		}

		return $this->buildPageResponse($request);
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function normalizeRequest(array $payload): array {
		$mode = 'page';
		$allowedModes = ['page', 'record', 'save', 'delete'];

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

		$id = '';
		if(isset($payload['id']) && is_scalar($payload['id'])) {
			$id = trim((string) $payload['id']);
		}

		return [
			'mode' => $mode,
			'page' => $page,
			'pageSize' => $pageSize,
			'search' => $search,
			'id' => $id,
			'sort' => $this->normalizeSort($payload['sort'] ?? null),
			'filters' => $this->normalizeFilters($payload['filters'] ?? null),
		];
	}

	/**
	 * @param mixed $sortPayload
	 * @return array<string, string>
	 */
	private function normalizeSort(mixed $sortPayload): array {
		$allowedKeys = ['pref_key', 'value_type', 'default_scope', 'sort_order', 'enabled', 'updated'];

		$sort = [
			'key' => 'sort_order',
			'dir' => 'asc',
			'type' => 'number',
		];

		if(!is_array($sortPayload) || count($sortPayload) === 0) {
			return $sort;
		}

		$first = reset($sortPayload);

		if(!is_array($first)) {
			return $sort;
		}

		$key = isset($first['key']) ? (string) $first['key'] : 'sort_order';
		if(!in_array($key, $allowedKeys, true)) {
			$key = 'sort_order';
		}

		$dir = isset($first['dir']) ? strtolower((string) $first['dir']) : 'asc';
		$dir = $dir === 'desc' ? 'desc' : 'asc';

		$type = $key === 'sort_order' || $key === 'enabled' ? 'number' : 'string';

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
			'pref_key' => '',
			'value_type' => '',
			'default_scope' => '',
			'enabled' => '',
		];

		if(!is_array($filtersPayload)) {
			return $result;
		}

		foreach(array_keys($result) as $key) {
			if(isset($filtersPayload[$key]) && is_scalar($filtersPayload[$key])) {
				$result[$key] = trim((string) $filtersPayload[$key]);
			}
		}

		if($result['value_type'] !== '' && !in_array($result['value_type'], $this->getAllowedValueTypes(), true)) {
			$result['value_type'] = '';
		}

		if($result['default_scope'] !== '' && !in_array($result['default_scope'], $this->getAllowedScopes(), true)) {
			$result['default_scope'] = '';
		}

		if($result['enabled'] !== '' && !in_array($result['enabled'], ['0', '1'], true)) {
			$result['enabled'] = '';
		}

		return $result;
	}

	/**
	 * @param array<string, mixed> $request
	 * @return array<string, mixed>
	 */
	private function buildPageResponse(array $request): array {
		$rows = $this->loadRows();
		$filteredRows = [];

		foreach($rows as $row) {
			if(!$this->matchesSearch($row, $request['search'])) {
				continue;
			}

			if(!$this->matchesFilters($row, $request['filters'])) {
				continue;
			}

			$filteredRows[] = $row;
		}

		usort(
			$filteredRows,
			fn(array $a, array $b) => $this->compareRows($a, $b, $request['sort'])
		);

		$total = count($filteredRows);
		$page = (int) $request['page'];
		$pageSize = (int) $request['pageSize'];
		$totalPages = $pageSize > 0 ? (int) ceil($total / $pageSize) : 0;
		$offset = max(0, ($page - 1) * $pageSize);
		$data = array_slice($filteredRows, $offset, $pageSize);

		return [
			'ok' => true,
			'mode' => 'page',
			'data' => array_values($data),
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
	 * @return array<string, mixed>
	 */
	private function buildRecordResponse(string $id): array {
		$row = $this->loadRowById($this->normalizeId($id));

		if($row === null) {
			return [
				'ok' => false,
				'mode' => 'record',
				'found' => false,
				'error' => 'User preference definition not found.',
				'record' => null,
			];
		}

		return [
			'ok' => true,
			'mode' => 'record',
			'found' => true,
			'record' => $row,
		];
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function buildSaveResponse(array $payload): array {
		$id = $this->normalizeId($this->readString($payload, 'id'));
		$prefKey = $this->readString($payload, 'pref_key');
		$description = $this->readNullableString($payload, 'description');
		$systemTemplate = $this->readString($payload, 'system_template');
		$valueType = $this->normalizeValueType($this->readString($payload, 'value_type'));
		$defaultScope = $this->normalizeDefaultScope($this->readString($payload, 'default_scope'));
		$sortOrder = $this->readInt($payload, 'sort_order', 100);
		$enabled = $this->readBoolAsInt($payload, 'enabled', true);

		try {
			$this->validatePrefKey($prefKey);
			$this->validateSystemTemplate($systemTemplate);
			$allowedValues = $this->normalizeAllowedValues($payload['allowed_values'] ?? null, $valueType);
			$this->assertUniquePrefKey($prefKey, $id);

			if($id > 0) {
				$this->updateDefinition($id, $prefKey, $description, $systemTemplate, $valueType, $allowedValues, $defaultScope, $sortOrder, $enabled);
			}
			else {
				$id = $this->insertDefinition($prefKey, $description, $systemTemplate, $valueType, $allowedValues, $defaultScope, $sortOrder, $enabled);
			}
		}
		catch(Throwable $e) {
			return $this->buildErrorResponse($e->getMessage(), 'save');
		}

		$row = $this->loadRowById($id);

		return [
			'ok' => true,
			'mode' => 'save',
			'action' => 'saved',
			'record' => $row,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildDeleteResponse(string $id): array {
		$dbId = $this->normalizeId($id);
		$row = $this->loadRowById($dbId);

		if($row === null) {
			return $this->buildErrorResponse('User preference definition not found.', 'delete');
		}

		$this->database->connect();
		$this->database->nonQuery(
			"DELETE FROM base3_missionbay_userpref_def WHERE id=" . (int) $dbId . " LIMIT 1"
		);

		return [
			'ok' => true,
			'mode' => 'delete',
			'action' => 'deleted',
			'id' => $dbId,
			'pref_key' => $row['pref_key'],
		];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function loadRows(): array {
		$this->ensureTable();

		$q = "SELECT id, pref_key, description, system_template, value_type, allowed_values, default_scope, sort_order, enabled, created, updated
			FROM base3_missionbay_userpref_def
			ORDER BY sort_order ASC, pref_key ASC";

		$rows = $this->database->multiQuery($q) ?? [];
		$result = [];

		foreach($rows as $row) {
			$result[] = $this->normalizeRow($row);
		}

		return $result;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function loadRowById(int $id): ?array {
		if($id <= 0) {
			return null;
		}

		$this->ensureTable();

		$q = "SELECT id, pref_key, description, system_template, value_type, allowed_values, default_scope, sort_order, enabled, created, updated
			FROM base3_missionbay_userpref_def
			WHERE id=" . (int) $id . "
			LIMIT 1";

		$row = $this->database->singleQuery($q);

		if(!$row) {
			return null;
		}

		return $this->normalizeRow($row);
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function normalizeRow(array $row): array {
		$id = (int) ($row['id'] ?? 0);
		$allowedValues = $row['allowed_values'] ?? null;
		$allowedValuesText = $this->formatAllowedValues($allowedValues);
		$systemTemplate = (string) ($row['system_template'] ?? '');
		$description = (string) ($row['description'] ?? '');
		$valueType = $this->normalizeValueType((string) ($row['value_type'] ?? 'string'));
		$defaultScope = $this->normalizeDefaultScope((string) ($row['default_scope'] ?? 'user'));
		$enabled = (int) ($row['enabled'] ?? 0) === 1;

		return [
			'id' => (string) $id,
			'db_id' => $id,
			'pref_key' => (string) ($row['pref_key'] ?? ''),
			'description' => $description,
			'system_template' => $systemTemplate,
			'system_template_preview' => $this->shorten($systemTemplate, 220),
			'value_type' => $valueType,
			'allowed_values' => $allowedValues === null ? null : (string) $allowedValues,
			'allowed_values_edit' => $allowedValuesText,
			'allowed_values_preview' => $this->shorten($allowedValuesText, 220),
			'default_scope' => $defaultScope,
			'sort_order' => (int) ($row['sort_order'] ?? 100),
			'enabled' => $enabled ? 1 : 0,
			'enabled_bool' => $enabled,
			'enabled_label' => $enabled ? 'Enabled' : 'Disabled',
			'created' => (string) ($row['created'] ?? ''),
			'updated' => (string) ($row['updated'] ?? ''),
		];
	}

	/**
	 * @param array<string, mixed> $a
	 * @param array<string, mixed> $b
	 * @param array<string, string> $sort
	 */
	private function compareRows(array $a, array $b, array $sort): int {
		$key = $sort['key'] ?? 'sort_order';
		$dir = $sort['dir'] ?? 'asc';
		$type = $sort['type'] ?? 'string';

		if($type === 'number') {
			$result = ((int) ($a[$key] ?? 0)) <=> ((int) ($b[$key] ?? 0));
		}
		else {
			$result = strnatcasecmp((string) ($a[$key] ?? ''), (string) ($b[$key] ?? ''));
		}

		if($result === 0) {
			$result = ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0));
		}

		if($result === 0) {
			$result = strnatcasecmp((string) ($a['pref_key'] ?? ''), (string) ($b['pref_key'] ?? ''));
		}

		return $dir === 'desc' ? -$result : $result;
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function matchesSearch(array $row, string $search): bool {
		if($search === '') {
			return true;
		}

		$needle = $this->toLower($search);
		$haystack = $this->toLower(
			implode(
				' ',
				[
					(string) ($row['pref_key'] ?? ''),
					(string) ($row['description'] ?? ''),
					(string) ($row['system_template'] ?? ''),
					(string) ($row['value_type'] ?? ''),
					(string) ($row['allowed_values_edit'] ?? ''),
					(string) ($row['default_scope'] ?? ''),
				]
			)
		);

		return strpos($haystack, $needle) !== false;
	}

	/**
	 * @param array<string, mixed> $row
	 * @param array<string, string> $filters
	 */
	private function matchesFilters(array $row, array $filters): bool {
		if($filters['pref_key'] !== '' && strpos($this->toLower((string) $row['pref_key']), $this->toLower($filters['pref_key'])) === false) {
			return false;
		}

		if($filters['value_type'] !== '' && (string) $row['value_type'] !== $filters['value_type']) {
			return false;
		}

		if($filters['default_scope'] !== '' && (string) $row['default_scope'] !== $filters['default_scope']) {
			return false;
		}

		if($filters['enabled'] !== '' && (string) $row['enabled'] !== $filters['enabled']) {
			return false;
		}

		return true;
	}

	private function insertDefinition(string $prefKey, ?string $description, string $systemTemplate, string $valueType, ?string $allowedValues, string $defaultScope, int $sortOrder, int $enabled): int {
		$this->ensureTable();

		$descriptionSql = $description !== null ? "'" . $this->database->escape($description) . "'" : 'NULL';
		$allowedValuesSql = $allowedValues !== null ? "'" . $this->database->escape($allowedValues) . "'" : 'NULL';

		$q = "INSERT INTO base3_missionbay_userpref_def (pref_key, description, system_template, value_type, allowed_values, default_scope, sort_order, enabled)
			VALUES (
				'" . $this->database->escape($prefKey) . "',
				$descriptionSql,
				'" . $this->database->escape($systemTemplate) . "',
				'" . $this->database->escape($valueType) . "',
				$allowedValuesSql,
				'" . $this->database->escape($defaultScope) . "',
				" . (int) $sortOrder . ",
				" . (int) $enabled . "
			)";

		$this->database->nonQuery($q);
		return (int) $this->database->insertId();
	}

	private function updateDefinition(int $id, string $prefKey, ?string $description, string $systemTemplate, string $valueType, ?string $allowedValues, string $defaultScope, int $sortOrder, int $enabled): void {
		$this->ensureTable();

		if($this->loadRowById($id) === null) {
			throw new InvalidArgumentException('User preference definition not found.');
		}

		$descriptionSql = $description !== null ? "'" . $this->database->escape($description) . "'" : 'NULL';
		$allowedValuesSql = $allowedValues !== null ? "'" . $this->database->escape($allowedValues) . "'" : 'NULL';

		$q = "UPDATE base3_missionbay_userpref_def SET
				pref_key='" . $this->database->escape($prefKey) . "',
				description=$descriptionSql,
				system_template='" . $this->database->escape($systemTemplate) . "',
				value_type='" . $this->database->escape($valueType) . "',
				allowed_values=$allowedValuesSql,
				default_scope='" . $this->database->escape($defaultScope) . "',
				sort_order=" . (int) $sortOrder . ",
				enabled=" . (int) $enabled . "
			WHERE id=" . (int) $id . "
			LIMIT 1";

		$this->database->nonQuery($q);
	}

	private function ensureTable(): void {
		$this->database->connect();

		$this->database->nonQuery("
			CREATE TABLE IF NOT EXISTS base3_missionbay_userpref_def (
				id BIGINT AUTO_INCREMENT PRIMARY KEY,
				pref_key VARCHAR(100) NOT NULL,
				description VARCHAR(255) NULL,
				system_template TEXT NOT NULL,
				value_type VARCHAR(20) NOT NULL DEFAULT 'string',
				allowed_values LONGTEXT NULL,
				default_scope VARCHAR(20) NOT NULL DEFAULT 'user',
				sort_order INT NOT NULL DEFAULT 100,
				enabled TINYINT(1) NOT NULL DEFAULT 1,
				created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				UNIQUE KEY uq_pref_key (pref_key),
				KEY idx_enabled_sort (enabled, sort_order)
			)
		");
	}

	private function validatePrefKey(string $prefKey): void {
		if($prefKey === '') {
			throw new InvalidArgumentException('Preference key must not be empty.');
		}

		if(strlen($prefKey) > 100) {
			throw new InvalidArgumentException('Preference key must not be longer than 100 characters.');
		}

		if(preg_match('/^[A-Za-z0-9_.:-]+$/', $prefKey) !== 1) {
			throw new InvalidArgumentException('Preference key may only contain letters, numbers, underscore, dot, colon and dash.');
		}
	}

	private function validateSystemTemplate(string $systemTemplate): void {
		if(trim($systemTemplate) === '') {
			throw new InvalidArgumentException('System template must not be empty.');
		}
	}

	private function assertUniquePrefKey(string $prefKey, int $currentId): void {
		$this->ensureTable();

		$q = "SELECT id FROM base3_missionbay_userpref_def
			WHERE pref_key='" . $this->database->escape($prefKey) . "'";

		if($currentId > 0) {
			$q .= " AND id<>" . (int) $currentId;
		}

		$q .= " LIMIT 1";

		$row = $this->database->singleQuery($q);

		if($row) {
			throw new InvalidArgumentException('Preference key already exists.');
		}
	}

	private function normalizeAllowedValues(mixed $value, string $valueType): ?string {
		if($valueType === 'bool') {
			return null;
		}

		if(is_array($value)) {
			$decoded = $value;
		}
		else {
			$text = trim((string) ($value ?? ''));

			if($text === '') {
				return null;
			}

			$decoded = json_decode($text, true);

			if(json_last_error() !== JSON_ERROR_NONE) {
				throw new InvalidArgumentException('Allowed values must contain valid JSON: ' . json_last_error_msg());
			}
		}

		if(!is_array($decoded)) {
			throw new InvalidArgumentException('Allowed values must decode to a JSON array.');
		}

		if(!$this->isListArray($decoded)) {
			throw new InvalidArgumentException('Allowed values must be a JSON list, for example ["Du", "Sie"].');
		}

		$normalized = [];
		foreach($decoded as $entry) {
			if(!is_scalar($entry)) {
				throw new InvalidArgumentException('Allowed values may only contain scalar values.');
			}

			$entryValue = trim((string) $entry);
			if($entryValue === '') {
				throw new InvalidArgumentException('Allowed values must not contain empty entries.');
			}

			$normalized[] = $entryValue;
		}

		if($valueType === 'enum' && count($normalized) === 0) {
			throw new InvalidArgumentException('Enum preference definitions need at least one allowed value.');
		}

		if(count($normalized) === 0) {
			return null;
		}

		$json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if(!is_string($json)) {
			throw new InvalidArgumentException('Allowed values could not be encoded.');
		}

		return $json;
	}

	private function formatAllowedValues(mixed $value): string {
		if($value === null || $value === '') {
			return '';
		}

		$decoded = json_decode((string) $value, true);

		if(json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
			return (string) $value;
		}

		$json = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

		return is_string($json) ? $json : (string) $value;
	}

	private function normalizeValueType(string $type): string {
		$type = strtolower(trim($type));

		if(!in_array($type, $this->getAllowedValueTypes(), true)) {
			return 'string';
		}

		return $type;
	}

	private function normalizeDefaultScope(string $scope): string {
		$scope = strtolower(trim($scope));

		if(!in_array($scope, $this->getAllowedScopes(), true)) {
			return 'user';
		}

		return $scope;
	}

	private function normalizeId(string $id): int {
		$id = trim($id);

		if($id === '' || preg_match('/^\d+$/', $id) !== 1) {
			return 0;
		}

		return (int) $id;
	}

	/**
	 * @return array<int, string>
	 */
	private function getAllowedValueTypes(): array {
		return ['string', 'enum', 'bool'];
	}

	/**
	 * @return array<int, string>
	 */
	private function getAllowedScopes(): array {
		return ['user', 'session'];
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private function getValueTypeOptions(bool $withEmpty): array {
		$options = [];

		if($withEmpty) {
			$options[] = [
				'value' => '',
				'label' => 'All types',
			];
		}

		$options[] = [
			'value' => 'string',
			'label' => 'String',
		];
		$options[] = [
			'value' => 'enum',
			'label' => 'Enum',
		];
		$options[] = [
			'value' => 'bool',
			'label' => 'Boolean',
		];

		return $options;
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private function getScopeOptions(bool $withEmpty): array {
		$options = [];

		if($withEmpty) {
			$options[] = [
				'value' => '',
				'label' => 'All scopes',
			];
		}

		$options[] = [
			'value' => 'user',
			'label' => 'User',
		];
		$options[] = [
			'value' => 'session',
			'label' => 'Session',
		];

		return $options;
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private function getEnabledOptions(bool $withEmpty): array {
		$options = [];

		if($withEmpty) {
			$options[] = [
				'value' => '',
				'label' => 'All states',
			];
		}

		$options[] = [
			'value' => '1',
			'label' => 'Enabled',
		];
		$options[] = [
			'value' => '0',
			'label' => 'Disabled',
		];

		return $options;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function readString(array $payload, string $key): string {
		if(!isset($payload[$key]) || !is_scalar($payload[$key])) {
			return '';
		}

		return trim((string) $payload[$key]);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function readNullableString(array $payload, string $key): ?string {
		if(!isset($payload[$key]) || !is_scalar($payload[$key])) {
			return null;
		}

		$value = trim((string) $payload[$key]);
		return $value === '' ? null : $value;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function readInt(array $payload, string $key, int $default): int {
		if(!isset($payload[$key]) || !is_scalar($payload[$key])) {
			return $default;
		}

		$value = trim((string) $payload[$key]);

		if($value === '' || preg_match('/^-?\d+$/', $value) !== 1) {
			return $default;
		}

		return (int) $value;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function readBoolAsInt(array $payload, string $key, bool $default): int {
		if(!isset($payload[$key])) {
			return $default ? 1 : 0;
		}

		$value = $payload[$key];

		if(is_bool($value)) {
			return $value ? 1 : 0;
		}

		if(is_int($value)) {
			return $value === 1 ? 1 : 0;
		}

		$text = strtolower(trim((string) $value));
		return in_array($text, ['1', 'true', 'yes', 'on', 'enabled'], true) ? 1 : 0;
	}

	/**
	 * @param array<string, mixed> $value
	 */
	private function isListArray(array $value): bool {
		return $value === [] || array_keys($value) === range(0, count($value) - 1);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildErrorResponse(string $message, string $mode): array {
		return [
			'ok' => false,
			'mode' => $mode,
			'error' => $message,
		];
	}

	private function shorten(string $value, int $maxLength): string {
		if(function_exists('mb_strlen') && function_exists('mb_substr')) {
			if(mb_strlen($value) <= $maxLength) {
				return $value;
			}

			return mb_substr($value, 0, $maxLength - 1) . '…';
		}

		if(strlen($value) <= $maxLength) {
			return $value;
		}

		return substr($value, 0, $maxLength - 1) . '…';
	}

	private function toLower(string $value): string {
		if(function_exists('mb_strtolower')) {
			return mb_strtolower($value);
		}

		return strtolower($value);
	}

}
