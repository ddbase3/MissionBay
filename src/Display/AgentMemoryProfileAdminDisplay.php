<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Profile\AgentContextProfileResolver;
use MissionBay\Profile\AgentMemoryProfileResolver;
use Throwable;

/**
 * Administrates profiles of already configured conversation-memory presets.
 *
 * AgentContextProfileAdminDisplay reuses the same profile editor and switches
 * only the settings group, labels and resolver.
 */
class AgentMemoryProfileAdminDisplay implements IDisplay {

	private const BATCH_SIZE = 50;

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly IAssetResolver $assetResolver,
		private readonly ISettingsStore $settingsStore,
		private readonly ILinkTargetService $linkTargetService,
		private readonly AgentMemoryProfileResolver $memoryProfiles,
		private readonly AgentContextProfileResolver $contextProfiles
	) {}

	public static function getName(): string {
		return 'agentmemoryprofileadmindisplay';
	}

	public function setData($data) {
		// no-op
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		if (strtolower($out) === 'json') {
			return $this->handleJson($final);
		}
		return $this->handleHtml();
	}

	public function getHelp(): string {
		return 'Administrates MissionBay ' . $this->profileKind() . ' profiles.';
	}

	protected function profileKind(): string {
		return 'memory';
	}

	protected function profileTitle(): string {
		return 'Memory Profiles';
	}

	protected function profileDescription(): string {
		return 'Memory profiles select already configured conversation-memory component presets. Preset configuration is reused unchanged.';
	}

	protected function presetLabel(): string {
		return 'Conversation-memory presets';
	}

	protected function emptyPresetText(): string {
		return 'No conversation-memory component presets are available.';
	}

	private function handleHtml(): string {
		$this->view->setPath(DIR_PLUGIN . 'MissionBay');
		$this->view->setTemplate('Display/AgentMemoryProfileAdminDisplay.php');
		$this->view->assign('service', $this->linkTargetService->getLink([
			'name' => static::getName(),
			'out' => 'json'
		]));
		$this->view->assign('settings_group', $this->settingsGroup());
		$this->view->assign('profile_kind', $this->profileKind());
		$this->view->assign('profile_title', $this->profileTitle());
		$this->view->assign('profile_description', $this->profileDescription());
		$this->view->assign('preset_label', $this->presetLabel());
		$this->view->assign('empty_preset_text', $this->emptyPresetText());
		$this->view->assign('preset_options', $this->presetOptions());
		$this->view->assign('component_preset_admin_url', $this->linkTargetService->getLink([
			'name' => AgentComponentPresetAdminDisplay::getName()
		]));
		$this->view->assign('resolve', fn($src) => $this->assetResolver->resolve((string)$src));
		return $this->view->loadTemplate();
	}

	private function handleJson(bool $final): string {
		try {
			$response = $this->buildJsonResponse();
		}
		catch (Throwable $e) {
			$response = [
				'ok' => false,
				'mode' => 'error',
				'error' => ucfirst($this->profileKind()) . ' profile admin request failed.',
				'details' => $e->getMessage()
			];
		}

		if ($final && !headers_sent()) {
			header('Content-Type: application/json; charset=utf-8');
		}

		return (string)json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	}

	/** @return array<string,mixed> */
	private function buildJsonResponse(): array {
		$payload = $this->request->getJsonBody();
		$payload = is_array($payload) ? $payload : [];
		$mode = is_scalar($payload['mode'] ?? null) ? strtolower(trim((string)$payload['mode'])) : 'page';

		return match ($mode) {
			'record' => $this->buildRecordResponse((string)($payload['id'] ?? '')),
			'save' => $this->buildSaveResponse($payload),
			'delete' => $this->buildDeleteResponse((string)($payload['id'] ?? '')),
			'reload' => $this->buildReloadResponse(),
			default => $this->buildPageResponse($payload)
		};
	}

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	private function buildPageResponse(array $payload): array {
		$page = max(1, (int)($payload['page'] ?? 1));
		$pageSize = max(1, min(250, (int)($payload['pageSize'] ?? self::BATCH_SIZE)));
		$search = trim((string)($payload['search'] ?? ''));
		$filters = is_array($payload['filters'] ?? null) ? $payload['filters'] : [];
		$sort = $this->normalizeSort($payload['sort'] ?? null);
		$rows = $this->loadRows();

		if ($search !== '') {
			$needle = $this->lower($search);
			$rows = array_values(array_filter($rows, function(array $row) use ($needle): bool {
				return str_contains($this->lower(implode("\n", [
					(string)$row['profile_id'],
					(string)$row['label'],
					(string)$row['description'],
					(string)$row['preset_text'],
					(string)$row['profile_json']
				])), $needle);
			}));
		}

		if (($filters['enabled'] ?? '') !== '') {
			$enabled = $this->toBool($filters['enabled']);
			$rows = array_values(array_filter($rows, static fn(array $row): bool => (bool)$row['enabled'] === $enabled));
		}

		usort($rows, function(array $left, array $right) use ($sort): int {
			$key = $sort['key'];
			$result = $key === 'preset_count'
				? ((int)$left[$key]) <=> ((int)$right[$key])
				: strcmp($this->lower((string)$left[$key]), $this->lower((string)$right[$key]));
			if ($result === 0) $result = strcmp((string)$left['profile_id'], (string)$right['profile_id']);
			return $sort['dir'] === 'desc' ? -$result : $result;
		});

		$total = count($rows);
		$offset = ($page - 1) * $pageSize;
		$data = array_map(static fn(array $row): array => [
			'id' => $row['id'],
			'profile_id' => $row['profile_id'],
			'label' => $row['label'],
			'description' => $row['description'],
			'enabled' => $row['enabled'],
			'enabled_label' => $row['enabled_label'],
			'presets' => $row['presets'],
			'preset_count' => $row['preset_count'],
			'preset_text' => $row['preset_text'],
			'legacy_derived' => $row['legacy_derived']
		], array_slice($rows, $offset, $pageSize));

		return [
			'ok' => true,
			'mode' => 'page',
			'data' => $data,
			'page' => $page,
			'pageSize' => $pageSize,
			'total' => $total,
			'totalPages' => (int)ceil($total / $pageSize),
			'hasMore' => ($offset + $pageSize) < $total,
			'appliedSearch' => $search,
			'appliedSort' => [$sort],
			'appliedFilters' => $filters
		];
	}

	/** @return array<string,mixed> */
	private function buildRecordResponse(string $id): array {
		$id = $this->normalizeId($id);
		if ($id === '') {
			return $this->error('Missing profile id.', 'record');
		}

		try {
			$profile = $this->getProfile($id);
		}
		catch (Throwable $e) {
			return $this->error($e->getMessage(), 'record');
		}

		return [
			'ok' => true,
			'mode' => 'record',
			'record' => $this->normalizeRow($profile),
			'preset_options' => $this->presetOptions()
		];
	}

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	private function buildSaveResponse(array $payload): array {
		$oldId = $this->normalizeId((string)($payload['old_id'] ?? ''));
		$id = $this->normalizeId((string)($payload['id'] ?? ''));
		$label = trim((string)($payload['label'] ?? ''));
		$description = trim((string)($payload['description'] ?? ''));
		$enabled = $this->toBool($payload['enabled'] ?? true);
		$presets = $this->normalizePresetSelection($payload['presets'] ?? []);

		if ($id === '') return $this->error('Profile id must not be empty.', 'save');
		if ($label === '') $label = $id;

		$available = [];
		foreach ($this->presetOptions() as $option) $available[(string)$option['id']] = true;
		foreach ($presets as $presetId) {
			if (!isset($available[$presetId])) {
				return $this->error('Selected configured preset is not available for this profile: ' . $presetId, 'save');
			}
		}

		$isRename = $oldId !== '' && $oldId !== $id;
		if ($isRename && $this->settingsStore->has($this->settingsGroup(), $id)) {
			return $this->error('Target profile already exists: ' . $id, 'save');
		}

		$profile = [
			'id' => $id,
			'label' => $label,
			'description' => $description,
			'enabled' => $enabled,
			$this->presetField() => $presets
		];

		try {
			if ($this->profileKind() === 'memory') {
				$this->migrateLegacyContextProfile($oldId !== '' ? $oldId : $id, $id);
			}
			$this->settingsStore->set($this->settingsGroup(), $id, $profile);
			if ($isRename) $this->settingsStore->remove($this->settingsGroup(), $oldId);
			$this->settingsStore->save();
		}
		catch (Throwable $e) {
			return $this->error('Profile could not be saved: ' . $e->getMessage(), 'save');
		}

		return [
			'ok' => true,
			'mode' => 'save',
			'action' => $isRename ? 'renamed and saved' : 'saved',
			'record' => $this->normalizeRow($this->normalizeProfile($id, $profile))
		];
	}

	/** @return array<string,mixed> */
	private function buildDeleteResponse(string $id): array {
		$id = $this->normalizeId($id);
		if ($id === '' || !$this->settingsStore->has($this->settingsGroup(), $id)) {
			return $this->error('Profile not found or is only a legacy-derived preview: ' . $id, 'delete');
		}

		try {
			$this->settingsStore->remove($this->settingsGroup(), $id);
			$this->settingsStore->save();
		}
		catch (Throwable $e) {
			return $this->error('Profile could not be deleted: ' . $e->getMessage(), 'delete');
		}
		return ['ok' => true, 'mode' => 'delete', 'action' => 'deleted', 'id' => $id];
	}

	/** @return array<string,mixed> */
	private function buildReloadResponse(): array {
		try {
			$this->settingsStore->reload();
		}
		catch (Throwable $e) {
			return $this->error('Profile store could not be reloaded: ' . $e->getMessage(), 'reload');
		}
		return ['ok' => true, 'mode' => 'reload', 'action' => 'reloaded', 'preset_options' => $this->presetOptions()];
	}

	/** @return array<int,array<string,mixed>> */
	private function loadRows(): array {
		$rows = [];
		foreach ($this->profileOptions() as $option) {
			try {
				$rows[] = $this->normalizeRow($this->getProfile((string)$option['id']));
			}
			catch (Throwable) {
				// Invalid profiles stay out of the editor list instead of breaking it.
			}
		}
		return $rows;
	}

	/** @param array<string,mixed> $profile @return array<string,mixed> */
	private function normalizeRow(array $profile): array {
		$presetLabels = [];
		$options = [];
		foreach ($this->presetOptions() as $option) $options[(string)$option['id']] = $option;
		foreach ((array)($profile['presets'] ?? []) as $presetId) {
			$label = (string)($options[$presetId]['label'] ?? $presetId);
			$presetLabels[] = $label === $presetId ? $presetId : $label . ' (' . $presetId . ')';
		}
		$profile['presets'] = array_values((array)($profile['presets'] ?? []));
		$profileJson = (string)json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

		return array_merge($profile, [
			'old_id' => $profile['id'],
			'profile_id' => $profile['id'],
			'enabled_label' => $profile['enabled'] ? 'enabled' : 'disabled',
			'preset_count' => count($profile['presets']),
			'preset_text' => implode(', ', $presetLabels),
			'legacy_derived' => (bool)($profile['legacy_derived'] ?? false),
			'profile_json' => $profileJson
		]);
	}

	private function migrateLegacyContextProfile(string $sourceId, string $targetId): void {
		$sourceId = $this->normalizeId($sourceId);
		$targetId = $this->normalizeId($targetId);
		if ($sourceId === '' || $targetId === '' || $this->settingsStore->has(AgentContextProfileResolver::SETTINGS_GROUP, $targetId)) {
			return;
		}

		try {
			$derived = $this->contextProfiles->getProfile($sourceId);
		}
		catch (Throwable) {
			return;
		}
		if (empty($derived['legacy_derived']) || empty($derived['presets'])) {
			return;
		}

		$legacy = $this->settingsStore->get(AgentMemoryProfileResolver::SETTINGS_GROUP, $sourceId, []);
		$label = is_array($legacy) ? trim((string)($legacy['label'] ?? '')) : '';
		$this->settingsStore->set(AgentContextProfileResolver::SETTINGS_GROUP, $targetId, [
			'id' => $targetId,
			'label' => $label !== '' ? $label . ' Context' : $targetId,
			'description' => is_array($legacy) ? trim((string)($legacy['description'] ?? '')) : '',
			'enabled' => is_array($legacy) ? $this->toBool($legacy['enabled'] ?? true) : true,
			AgentContextProfileResolver::PRESET_FIELD => array_values((array)$derived['presets'])
		]);
	}

	/** @return AgentMemoryProfileResolver|AgentContextProfileResolver */
	private function resolver(): AgentMemoryProfileResolver|AgentContextProfileResolver {
		return $this->profileKind() === 'context' ? $this->contextProfiles : $this->memoryProfiles;
	}

	/** @return array<int,array<string,mixed>> */
	private function profileOptions(): array {
		return $this->resolver()->getOptions();
	}

	/** @return array<int,array<string,mixed>> */
	private function presetOptions(): array {
		return $this->resolver()->getPresetOptions();
	}

	/** @return array<string,mixed> */
	private function getProfile(string $id): array {
		return $this->resolver()->getProfile($id);
	}

	/** @param array<string,mixed> $settings @return array<string,mixed> */
	private function normalizeProfile(string $id, array $settings): array {
		return $this->resolver()->normalizeProfile($id, $settings);
	}

	private function settingsGroup(): string {
		return $this->profileKind() === 'context'
			? AgentContextProfileResolver::SETTINGS_GROUP
			: AgentMemoryProfileResolver::SETTINGS_GROUP;
	}

	private function presetField(): string {
		return $this->profileKind() === 'context'
			? AgentContextProfileResolver::PRESET_FIELD
			: AgentMemoryProfileResolver::PRESET_FIELD;
	}

	/** @return array<int,string> */
	private function normalizePresetSelection(mixed $value): array {
		if (is_string($value)) $value = explode(',', $value);
		if (!is_array($value)) return [];
		$result = [];
		foreach ($value as $presetId) {
			$presetId = $this->normalizeId((string)$presetId);
			if ($presetId !== '') $result[$presetId] = $presetId;
		}
		return array_values($result);
	}

	/** @return array{key:string,dir:string} */
	private function normalizeSort(mixed $sort): array {
		if (is_array($sort) && isset($sort[0]) && is_array($sort[0])) $sort = $sort[0];
		$sort = is_array($sort) ? $sort : [];
		$key = (string)($sort['key'] ?? 'profile_id');
		if (!in_array($key, ['profile_id', 'label', 'enabled_label', 'preset_count', 'description'], true)) $key = 'profile_id';
		$dir = strtolower((string)($sort['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
		return ['key' => $key, 'dir' => $dir];
	}

	private function normalizeId(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}

	private function lower(string $value): string {
		return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
	}

	private function toBool(mixed $value): bool {
		if (is_bool($value)) return $value;
		if (is_int($value) || is_float($value)) return $value !== 0;
		return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
	}

	/** @return array<string,mixed> */
	private function error(string $message, string $mode): array {
		return ['ok' => false, 'mode' => $mode, 'error' => $message];
	}
}
