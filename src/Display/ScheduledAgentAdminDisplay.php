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
use Base3\Worker\Api\IJobExecutionPolicy;
use JsonException;
use MissionBay\Api\IAgentConfigFormService;
use Throwable;

/**
 * ScheduledAgentAdminDisplay
 *
 * Provides a ModularGrid based administration surface for scheduled
 * MissionBay agent configurations stored in ISettingsStore.
 */
final class ScheduledAgentAdminDisplay implements IDisplay {

	private const SETTINGS_GROUP = 'scheduled-agent';
	private const BATCH_SIZE = 50;

	/**
	 * @var array<int,array<string,mixed>>|null
	 */
	private ?array $policyOptions = null;

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly IAssetResolver $assetResolver,
		private readonly ISettingsStore $settingsStore,
		private readonly ILinkTargetService $linkTargetService,
		private readonly IClassMap $classMap,
		private readonly IAgentConfigFormService $agentConfigFormService
	) {}

	public static function getName(): string {
		return 'scheduledagentadmindisplay';
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
		return 'Administrates scheduled MissionBay agent configurations.';
	}

	private function handleHtml(): string {
		$this->view->setPath(DIR_PLUGIN . 'MissionBay');
		$this->view->setTemplate('Display/ScheduledAgentAdminDisplay.php');

		$defaultRecord = $this->buildDefaultRecord();
		$formId = 'base3_scheduled_agent_admin_editor_form';

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
		$this->view->assign('policy_options', $this->listPolicyOptions());
		$this->view->assign('default_record', $defaultRecord);
		$this->view->assign('values', $defaultRecord);
		$this->view->assign('group', self::SETTINGS_GROUP);
		$this->view->assign('name', '');
		$this->view->assign('form_id', $formId);
		$this->view->assign('resolve', fn($src) => $this->assetResolver->resolve((string)$src));

		$this->agentConfigFormService->assignViewData($this->view, $defaultRecord, [
			'form_id' => $formId
		]);

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
				'error' => 'Scheduled agent admin request failed.',
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
		$payload = $this->getRequestPayload();
		$request = $this->normalizeRequest($payload);

		if($request['mode'] === 'record') {
			return $this->buildRecordResponse($request['id']);
		}

		if($request['mode'] === 'save') {
			return $this->buildSaveResponse();
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
	 * @return array<string,mixed>
	 */
	private function getRequestPayload(): array {
		$payload = $this->request->getJsonBody();

		if(!is_array($payload)) {
			$payload = [];
		}

		$mode = trim((string)$this->request->request('mode', ''));
		if($mode !== '') {
			$payload['mode'] = $mode;
		}

		$id = trim((string)$this->request->request('id', ''));
		if($id === '') {
			$id = trim((string)$this->request->request('agent_id', ''));
		}

		if($id !== '') {
			$payload['id'] = $id;
		}

		return $payload;
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
			$id = $this->normalizeTechnicalKey((string)$payload['id']);
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
				'agent_id' => $row['agent_id'],
				'label' => $row['label'],
				'enabled' => $row['enabled'],
				'enabled_label' => $row['enabled_label'],
				'policy' => $row['policy'],
				'policy_label' => $row['policy_label'],
				'policy_data_text' => $row['policy_data_text'],
				'llm' => $row['llm'],
				'component_count' => $row['component_count'],
				'user_prompt_preview' => $row['user_prompt_preview']
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
			return $this->buildErrorResponse('Missing scheduled agent id.', 'record');
		}

		if(!$this->settingsStore->has(self::SETTINGS_GROUP, $id)) {
			return $this->buildErrorResponse('Scheduled agent not found: ' . $id, 'record');
		}

		$settings = $this->settingsStore->get(self::SETTINGS_GROUP, $id, []);

		if(!is_array($settings)) {
			$settings = [];
		}

		return [
			'ok' => true,
			'mode' => 'record',
			'record' => $this->normalizeRow($id, $settings)
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function buildSaveResponse(): array {
		$oldId = $this->normalizeTechnicalKey((string)$this->request->request('old_id', ''));
		$id = $this->normalizeTechnicalKey((string)$this->request->request('agent_id', ''));

		if($id === '') {
			return $this->buildErrorResponse('Agent ID must not be empty.', 'save');
		}

		if($oldId !== '' && $oldId !== $id) {
			return $this->buildErrorResponse('Renaming scheduled agents is not supported in this editor.', 'save');
		}

		if($oldId === '' && $this->settingsStore->has(self::SETTINGS_GROUP, $id)) {
			return $this->buildErrorResponse('Scheduled agent already exists: ' . $id, 'save');
		}

		$errors = [];
		$settings = $this->getPostedSettings($errors);

		if($errors !== []) {
			return $this->buildErrorResponse(implode(' ', $errors), 'save');
		}

		try {
			$this->settingsStore->set(self::SETTINGS_GROUP, $id, $settings);
			$this->settingsStore->save();
		}
		catch(Throwable $e) {
			return $this->buildErrorResponse('Scheduled agent could not be saved: ' . $e->getMessage(), 'save');
		}

		return [
			'ok' => true,
			'mode' => 'save',
			'action' => 'saved',
			'record' => $this->normalizeRow($id, $settings)
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function getPostedSettings(array &$errors): array {
		$policyId = $this->normalizeTechnicalKey((string)$this->request->request('policy', ''));
		$policyData = $this->normalizePostedPolicyData($policyId, $errors);
		$agentSettings = $this->agentConfigFormService->getPostedSettings($errors);

		if($policyId === '') {
			$errors[] = 'Please select a timing policy.';
		}
		elseif(!$this->policyExists($policyId)) {
			$errors[] = 'Selected timing policy does not exist: ' . $policyId;
		}

		return $this->normalizeScheduledAgentSettings(array_merge([
			'enabled' => $this->request->request('enabled') !== null,
			'label' => $this->normalizeLabel((string)$this->request->request('label', '')),
			'user_prompt' => $this->normalizeTextBlock((string)$this->request->request('user_prompt', '')),
			'policy' => [
				'policy' => $policyId,
				'data' => $policyData
			]
		], $agentSettings));
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	private function normalizeScheduledAgentSettings(array $settings): array {
		$policy = is_array($settings['policy'] ?? null) ? $settings['policy'] : [];
		$policyId = $this->normalizeTechnicalKey((string)($policy['policy'] ?? ''));

		if($policyId === '') {
			$policyId = $this->getDefaultPolicyId();
		}

		$policyData = is_array($policy['data'] ?? null) ? $policy['data'] : [];

		return array_merge([
			'enabled' => $this->toBool($settings['enabled'] ?? true),
			'label' => $this->normalizeLabel((string)($settings['label'] ?? '')),
			'user_prompt' => $this->normalizeTextBlock((string)($settings['user_prompt'] ?? '')),
			'policy' => [
				'policy' => $policyId,
				'data' => $policyData
			]
		], $this->agentConfigFormService->normalizeSettings($settings));
	}

	/**
	 * @return array<string,mixed>
	 */
	private function buildDeleteResponse(string $id): array {
		$id = $this->normalizeTechnicalKey($id);

		if($id === '') {
			return $this->buildErrorResponse('Missing scheduled agent id.', 'delete');
		}

		if(!$this->settingsStore->has(self::SETTINGS_GROUP, $id)) {
			return $this->buildErrorResponse('Scheduled agent not found: ' . $id, 'delete');
		}

		try {
			$this->settingsStore->remove(self::SETTINGS_GROUP, $id);
			$this->settingsStore->save();
		}
		catch(Throwable $e) {
			return $this->buildErrorResponse('Scheduled agent could not be deleted: ' . $e->getMessage(), 'delete');
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
			return $this->buildErrorResponse('Scheduled agent store could not be reloaded: ' . $e->getMessage(), 'reload');
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
		$id = $this->normalizeTechnicalKey($id);
		$values = $this->settingsToViewValues($id, $settings);
		$policyId = (string)($values['policy_policy'] ?? '');
		$policyData = is_array($values['policy_data'] ?? null) ? $values['policy_data'] : [];
		$label = $this->normalizeLabel((string)($values['label'] ?? ''));
		$agentComponents = is_array($values['agent_components'] ?? null) ? $values['agent_components'] : [];
		$settingsJson = $this->encodePrettyJson($settings);

		return array_merge($values, [
			'id' => $id,
			'agent_id' => $id,
			'old_id' => $id,
			'label' => $label !== '' ? $label : $id,
			'scheduled_agent_config_group' => self::SETTINGS_GROUP,
			'scheduled_agent_config_name' => $id,
			'enabled_label' => !empty($values['enabled']) ? 'enabled' : 'disabled',
			'policy' => $policyId,
			'policy_label' => $this->getPolicyLabel($policyId),
			'policy_data_text' => $this->formatPolicyDataText($policyData),
			'component_count' => count($agentComponents),
			'user_prompt_preview' => $this->shorten((string)($values['user_prompt'] ?? ''), 160),
			'settings_json' => $settingsJson
		]);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function buildDefaultRecord(): array {
		$settings = array_merge([
			'enabled' => true,
			'label' => '',
			'user_prompt' => '',
			'policy' => [
				'policy' => $this->getDefaultPolicyId(),
				'data' => []
			]
		], $this->agentConfigFormService->getDefaultSettings());

		return $this->normalizeRow('', $settings);
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	private function settingsToViewValues(string $id, array $settings): array {
		$policy = is_array($settings['policy'] ?? null) ? $settings['policy'] : [];
		$policyId = $this->normalizeTechnicalKey((string)($policy['policy'] ?? ''));

		if($policyId === '') {
			$policyId = $this->getDefaultPolicyId();
		}

		$policyData = is_array($policy['data'] ?? null) ? $policy['data'] : [];

		return array_merge([
			'enabled' => $this->toBool($settings['enabled'] ?? true),
			'label' => $this->normalizeLabel((string)($settings['label'] ?? '')),
			'user_prompt' => $this->normalizeTextBlock((string)($settings['user_prompt'] ?? '')),
			'policy_policy' => $policyId,
			'policy_data' => $policyData,
			'scheduled_agent_config_group' => self::SETTINGS_GROUP,
			'scheduled_agent_config_name' => $id
		], $this->agentConfigFormService->settingsToViewValues($settings));
	}

	/**
	 * @param mixed $sortPayload
	 * @return array<string,string>
	 */
	private function normalizeSort(mixed $sortPayload): array {
		$allowedKeys = [
			'agent_id',
			'label',
			'enabled_label',
			'policy_label',
			'llm',
			'component_count',
			'user_prompt_preview'
		];

		$sort = [
			'key' => 'agent_id',
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

		$key = isset($first['key']) ? (string)$first['key'] : 'agent_id';
		if(!in_array($key, $allowedKeys, true)) {
			$key = 'agent_id';
		}

		$dir = isset($first['dir']) ? strtolower((string)$first['dir']) : 'asc';
		$dir = $dir === 'desc' ? 'desc' : 'asc';
		$type = in_array($key, ['component_count'], true) ? 'int' : 'string';

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

		foreach(['enabled', 'policy'] as $key) {
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
				(string)($row['agent_id'] ?? ''),
				(string)($row['label'] ?? ''),
				(string)($row['enabled_label'] ?? ''),
				(string)($row['policy'] ?? ''),
				(string)($row['policy_label'] ?? ''),
				(string)($row['llm'] ?? ''),
				(string)($row['user_prompt_preview'] ?? ''),
				(string)($row['settings_json'] ?? '')
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
			if(isset($filters['enabled'])) {
				$enabled = $this->toBool($row['enabled'] ?? false) ? '1' : '0';

				if($enabled !== (string)$filters['enabled']) {
					continue;
				}
			}

			if(isset($filters['policy']) && (string)($row['policy'] ?? '') !== (string)$filters['policy']) {
				continue;
			}

			$result[] = $row;
		}

		return $result;
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 * @param array<string,string> $sort
	 * @return array<int,array<string,mixed>>
	 */
	private function applySort(array $rows, array $sort): array {
		$key = $sort['key'] ?? 'agent_id';
		$dir = $sort['dir'] ?? 'asc';

		usort($rows, function(array $left, array $right) use ($key, $dir): int {
			if(in_array($key, ['component_count'], true)) {
				$result = ((int)($left[$key] ?? 0)) <=> ((int)($right[$key] ?? 0));
			}
			else {
				$result = strcmp($this->toLower((string)($left[$key] ?? '')), $this->toLower((string)($right[$key] ?? '')));
			}

			if($result === 0) {
				$result = strcmp($this->toLower((string)($left['agent_id'] ?? '')), $this->toLower((string)($right['agent_id'] ?? '')));
			}

			return $dir === 'desc' ? -$result : $result;
		});

		return $rows;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function listPolicyOptions(): array {
		if($this->policyOptions !== null) {
			return $this->policyOptions;
		}

		$rows = [];

		try {
			$policies = $this->classMap->getInstancesByInterface(IJobExecutionPolicy::class);
		}
		catch(Throwable) {
			$this->policyOptions = [];
			return [];
		}

		foreach($policies as $policy) {
			if(!$policy instanceof IJobExecutionPolicy) {
				continue;
			}

			$class = $policy::class;
			$id = $this->normalizeTechnicalKey((string)$class::getName());

			if($id === '') {
				continue;
			}

			$schema = $policy->getSchema();

			if(!is_array($schema)) {
				$schema = [];
			}

			$rows[$id] = [
				'id' => $id,
				'label' => $this->policyLabelFromClass($class, $id),
				'class' => $class,
				'schema' => $schema
			];
		}

		$rows = array_values($rows);

		usort($rows, static function(array $a, array $b): int {
			return strcasecmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
		});

		$this->policyOptions = $rows;

		return $rows;
	}

	private function policyLabelFromClass(string $class, string $fallback): string {
		$parts = explode('\\', $class);
		$short = end($parts);

		if(!is_string($short) || $short === '') {
			return $fallback;
		}

		$label = preg_replace('/(?<!^)[A-Z]/', ' $0', $short) ?? $short;
		$label = trim(str_replace(' Job Policy', '', $label));

		return $label !== '' ? $label : $fallback;
	}

	private function getPolicyLabel(string $id): string {
		foreach($this->listPolicyOptions() as $option) {
			if((string)($option['id'] ?? '') === $id) {
				$label = trim((string)($option['label'] ?? ''));
				return $label !== '' ? $label : $id;
			}
		}

		return $id;
	}

	private function policyExists(string $id): bool {
		if($id === '') {
			return false;
		}

		foreach($this->listPolicyOptions() as $option) {
			if((string)($option['id'] ?? '') === $id) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function getPolicySchema(string $id): array {
		foreach($this->listPolicyOptions() as $option) {
			if((string)($option['id'] ?? '') !== $id) {
				continue;
			}

			$schema = $option['schema'] ?? [];

			return is_array($schema) ? $schema : [];
		}

		return [];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function getPolicySchemaProperties(array $schema): array {
		if(is_array($schema['properties'] ?? null)) {
			return $schema['properties'];
		}

		if(is_array($schema['fields'] ?? null)) {
			return $schema['fields'];
		}

		$data = is_array($schema['data'] ?? null) ? $schema['data'] : [];

		if(is_array($data['properties'] ?? null)) {
			return $data['properties'];
		}

		return [];
	}

	/**
	 * @return array<int,string>
	 */
	private function getPolicySchemaRequired(array $schema): array {
		if(is_array($schema['required'] ?? null)) {
			return array_map('strval', $schema['required']);
		}

		$data = is_array($schema['data'] ?? null) ? $schema['data'] : [];

		if(is_array($data['required'] ?? null)) {
			return array_map('strval', $data['required']);
		}

		return [];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function normalizePostedPolicyData(string $policyId, array &$errors): array {
		$raw = [];
		$jsonRaw = $this->decodePostedBase64Field('policy_data_b64', 'Timing policy data', $errors);

		if($jsonRaw === '') {
			$jsonRaw = trim((string)$this->request->request('policy_data_json', ''));
		}

		if($jsonRaw !== '') {
			try {
				$decoded = json_decode($jsonRaw, true, 512, JSON_THROW_ON_ERROR);

				if(is_array($decoded)) {
					$raw = $decoded;
				}
			}
			catch(JsonException $e) {
				$errors[] = 'Timing policy data must be valid JSON: ' . $e->getMessage();
			}
		}

		if($raw === []) {
			$raw = $this->request->request('policy_data', []);

			if(!is_array($raw)) {
				$raw = [];
			}
		}

		$schema = $this->getPolicySchema($policyId);
		$properties = $this->getPolicySchemaProperties($schema);
		$required = $this->getPolicySchemaRequired($schema);
		$result = [];

		foreach($properties as $key => $property) {
			if(!is_string($key) || !is_array($property)) {
				continue;
			}

			$type = (string)($property['type'] ?? 'string');
			$value = $raw[$key] ?? null;
			$isRequired = in_array($key, $required, true);

			if(($value === null || $value === '') && !$isRequired) {
				continue;
			}

			if(($value === null || $value === '') && $isRequired) {
				$errors[] = 'Timing policy field "' . $key . '" is required.';
				continue;
			}

			if($type === 'integer') {
				if(!is_numeric($value)) {
					$errors[] = 'Timing policy field "' . $key . '" must be numeric.';
					continue;
				}

				$result[$key] = (int)$value;
				continue;
			}

			if($type === 'number') {
				if(!is_numeric($value)) {
					$errors[] = 'Timing policy field "' . $key . '" must be numeric.';
					continue;
				}

				$result[$key] = (float)$value;
				continue;
			}

			if($type === 'boolean') {
				$result[$key] = $this->toBool($value);
				continue;
			}

			if($type === 'object' || $type === 'array') {
				$decoded = $this->decodePolicyJsonField((string)$value, $key, $errors);

				if($decoded !== null) {
					$result[$key] = $decoded;
				}

				continue;
			}

			$value = trim((string)$value);
			$enum = is_array($property['enum'] ?? null) ? array_map('strval', $property['enum']) : [];

			if($enum !== [] && !in_array($value, $enum, true)) {
				$errors[] = 'Timing policy field "' . $key . '" has an invalid value.';
				continue;
			}

			$result[$key] = $value;
		}

		return $result;
	}

	private function decodePostedBase64Field(string $field, string $label, array &$errors): string {
		$raw = trim((string)$this->request->request($field, ''));

		if($raw === '') {
			return '';
		}

		$decoded = base64_decode($raw, true);

		if(!is_string($decoded)) {
			$errors[] = $label . ' could not be decoded from base64.';

			return '';
		}

		return trim($decoded);
	}

	private function decodePolicyJsonField(string $raw, string $key, array &$errors): mixed {
		$raw = trim($raw);

		if($raw === '') {
			return [];
		}

		try {
			return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
		}
		catch(JsonException $e) {
			$errors[] = 'Timing policy field "' . $key . '" must be valid JSON: ' . $e->getMessage();

			return null;
		}
	}

	private function getDefaultPolicyId(): string {
		$options = $this->listPolicyOptions();

		foreach(['manualonlyjobpolicy', 'dailyaftertimejobpolicy', 'dailywindowjobpolicy', 'cronjobpolicy', 'intervaljobpolicy'] as $preferred) {
			foreach($options as $option) {
				if((string)($option['id'] ?? '') === $preferred) {
					return $preferred;
				}
			}
		}

		$first = $options[0]['id'] ?? '';

		return is_string($first) ? $first : '';
	}

	/**
	 * @param array<string,mixed> $policyData
	 */
	private function formatPolicyDataText(array $policyData): string {
		if($policyData === []) {
			return '-';
		}

		$parts = [];

		foreach($policyData as $key => $value) {
			if(is_scalar($value) || $value === null) {
				$parts[] = (string)$key . ': ' . (string)$value;
				continue;
			}

			$parts[] = (string)$key . ': ' . $this->shorten($this->encodePrettyJson($value), 60);
		}

		return implode(', ', $parts);
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

	private function normalizeTextBlock(string $value): string {
		return str_replace(["\r\n", "\r"], "\n", $value);
	}

	private function normalizeLabel(string $value): string {
		return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
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

	private function toLower(string $value): string {
		return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
	}

	private function shorten(string $value, int $maxLength): string {
		$value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

		if($value === '') {
			return '';
		}

		if(strlen($value) <= $maxLength) {
			return $value;
		}

		return substr($value, 0, max(0, $maxLength - 3)) . '...';
	}

	private function encodePrettyJson(mixed $value): string {
		$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

		return is_string($json) ? $json : '';
	}
}
