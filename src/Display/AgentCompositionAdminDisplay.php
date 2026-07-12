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
use MissionBay\Composition\AgentCompositionInspector;
use MissionBay\Orchestrator\Profile\AgentOrchestratorProfileRepository;
use MissionBay\Profile\AgentToolProfileResolver;
use Throwable;

/**
 * Read-only administration view for the effective configured composition of
 * MissionBay agents.
 */
final class AgentCompositionAdminDisplay implements IDisplay {

	private const SETTINGS_GROUP = 'agent';
	private const BATCH_SIZE = 50;

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly IAssetResolver $assetResolver,
		private readonly ISettingsStore $settingsStore,
		private readonly ILinkTargetService $linkTargetService,
		private readonly AgentOrchestratorProfileRepository $orchestratorProfiles,
		private readonly AgentToolProfileResolver $toolProfiles,
		private readonly AgentCompositionInspector $compositionInspector
	) {}

	public static function getName(): string {
		return 'agentcompositionadmindisplay';
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
		return 'Shows the effective MissionBay composition of configured agents.';
	}

	private function handleHtml(): string {
		$this->view->setPath(DIR_PLUGIN . 'MissionBay');
		$this->view->setTemplate('Display/AgentCompositionAdminDisplay.php');
		$this->view->assign('service', $this->linkTargetService->getLink([
			'name' => self::getName(),
			'out' => 'json'
		]));
		$this->view->assign('orchestrator_options', $this->orchestratorProfiles->getOptions());
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
				'error' => 'Agent composition request failed.',
				'details' => $e->getMessage()
			];
		}

		if ($final && !headers_sent()) {
			header('Content-Type: application/json; charset=utf-8');
		}

		return (string)json_encode(
			$response,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
		);
	}

	/** @return array<string,mixed> */
	private function buildJsonResponse(): array {
		$payload = $this->request->getJsonBody();
		$payload = is_array($payload) ? $payload : [];
		$mode = strtolower(trim((string)($payload['mode'] ?? 'page')));

		return match ($mode) {
			'record' => $this->buildRecordResponse((string)($payload['id'] ?? '')),
			'reload' => $this->buildReloadResponse(),
			default => $this->buildPageResponse($payload)
		};
	}

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	private function buildPageResponse(array $payload): array {
		$rows = $this->loadRows();
		$search = trim((string)($payload['search'] ?? ''));
		$filters = is_array($payload['filters'] ?? null) ? $payload['filters'] : [];

		if ($search !== '') {
			$needle = $this->lower($search);
			$rows = array_values(array_filter($rows, function(array $row) use ($needle): bool {
				return str_contains($this->lower(implode("\n", [
					(string)$row['agent_id'],
					(string)$row['label'],
					(string)$row['llm'],
					(string)$row['orchestrator_profile'],
					(string)$row['tool_profile_text'],
					(string)$row['status_detail']
				])), $needle);
			}));
		}

		if (($filters['status'] ?? '') !== '') {
			$status = strtolower(trim((string)$filters['status']));
			$rows = array_values(array_filter($rows, static fn(array $row): bool => $row['status'] === $status));
		}
		if (($filters['enabled'] ?? '') !== '') {
			$enabled = $this->toBool($filters['enabled']);
			$rows = array_values(array_filter($rows, static fn(array $row): bool => $row['enabled'] === $enabled));
		}
		if (($filters['orchestrator_profile'] ?? '') !== '') {
			$profile = strtolower(trim((string)$filters['orchestrator_profile']));
			$rows = array_values(array_filter($rows, static fn(array $row): bool => $row['orchestrator_profile'] === $profile));
		}

		$sort = $this->normalizeSort($payload['sort'] ?? null);
		usort($rows, function(array $left, array $right) use ($sort): int {
			$key = $sort['key'];
			if (in_array($key, ['tool_profile_count'], true)) {
				$result = ((int)$left[$key]) <=> ((int)$right[$key]);
			}
			else {
				$result = strcmp($this->lower((string)$left[$key]), $this->lower((string)$right[$key]));
			}
			if ($result === 0) {
				$result = strcmp((string)$left['agent_id'], (string)$right['agent_id']);
			}
			return $sort['dir'] === 'desc' ? -$result : $result;
		});

		$page = max(1, (int)($payload['page'] ?? 1));
		$pageSize = max(1, min(250, (int)($payload['pageSize'] ?? self::BATCH_SIZE)));
		$total = count($rows);
		$offset = ($page - 1) * $pageSize;

		return [
			'ok' => true,
			'mode' => 'page',
			'data' => array_slice($rows, $offset, $pageSize),
			'groups' => [],
			'page' => $page,
			'pageSize' => $pageSize,
			'total' => $total,
			'totalPages' => (int)ceil($total / $pageSize),
			'hasMore' => ($offset + $pageSize) < $total,
			'nextCursor' => null,
			'appliedSearch' => $search,
			'appliedSort' => [$sort],
			'appliedFilters' => $filters,
			'appliedGroup' => []
		];
	}

	/** @return array<string,mixed> */
	private function buildRecordResponse(string $id): array {
		$id = $this->normalizeId($id);
		if ($id === '' || !$this->settingsStore->has(self::SETTINGS_GROUP, $id)) {
			return $this->error('Agent not found: ' . $id, 'record');
		}

		$settings = $this->settingsStore->get(self::SETTINGS_GROUP, $id, []);
		if (!is_array($settings)) {
			$settings = [];
		}

		$composition = $this->compositionInspector->inspect($id, $settings);
		$composition['composition_json'] = json_encode(
			$composition,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
		);

		return [
			'ok' => true,
			'mode' => 'record',
			'record' => $composition
		];
	}

	/** @return array<string,mixed> */
	private function buildReloadResponse(): array {
		try {
			$this->settingsStore->reload();
		}
		catch (Throwable $e) {
			return $this->error('Settings could not be reloaded: ' . $e->getMessage(), 'reload');
		}

		return ['ok' => true, 'mode' => 'reload', 'action' => 'reloaded'];
	}

	/** @return array<int,array<string,mixed>> */
	private function loadRows(): array {
		$group = $this->settingsStore->getGroup(self::SETTINGS_GROUP);
		if (!is_array($group)) {
			return [];
		}

		$availableToolProfiles = [];
		foreach ($this->toolProfiles->getOptions() as $option) {
			$availableToolProfiles[(string)($option['id'] ?? '')] = true;
		}

		$rows = [];
		foreach ($group as $id => $settings) {
			if ((!is_string($id) && !is_int($id)) || !is_array($settings)) {
				continue;
			}
			$agentId = $this->normalizeId((string)$id);
			$profileId = $this->normalizeId((string)($settings['orchestrator_profile'] ?? AgentOrchestratorProfileRepository::DEFAULT_PROFILE_ID));
			$toolProfileIds = $this->normalizeIds($settings['tool_profiles'] ?? []);
			$problems = [];
			try {
				$this->orchestratorProfiles->getProfile($profileId);
			}
			catch (Throwable $e) {
				$problems[] = $e->getMessage();
			}
			foreach ($toolProfileIds as $toolProfileId) {
				if (!isset($availableToolProfiles[$toolProfileId])) {
					$problems[] = 'Tool profile unavailable: ' . $toolProfileId;
				}
			}
			if (!$this->hasUsableFlow($settings['agent_flow'] ?? null)) {
				$problems[] = 'Agent flow is empty or invalid.';
			}

			$rows[] = [
				'id' => $agentId,
				'agent_id' => $agentId,
				'label' => trim((string)($settings['label'] ?? '')) ?: $agentId,
				'enabled' => $this->toBool($settings['enabled'] ?? true),
				'enabled_label' => $this->toBool($settings['enabled'] ?? true) ? 'enabled' : 'disabled',
				'llm' => trim((string)($settings['llm'] ?? '')),
				'orchestrator_profile' => $profileId,
				'tool_profile_count' => count($toolProfileIds),
				'tool_profile_text' => implode(', ', $toolProfileIds),
				'expert_overrides' => $this->toBool($settings['expert_overrides_enabled'] ?? false),
				'status' => $problems === [] ? 'valid' : 'error',
				'status_detail' => $problems === [] ? 'Configuration references are available.' : implode(' ', $problems)
			];
		}

		return $rows;
	}

	/** @return array{key:string,dir:string} */
	private function normalizeSort(mixed $sort): array {
		if (is_array($sort) && isset($sort[0]) && is_array($sort[0])) {
			$sort = $sort[0];
		}
		$sort = is_array($sort) ? $sort : [];
		$key = (string)($sort['key'] ?? 'agent_id');
		$allowed = ['agent_id', 'label', 'status', 'orchestrator_profile', 'tool_profile_count', 'llm'];
		if (!in_array($key, $allowed, true)) {
			$key = 'agent_id';
		}
		$dir = strtolower((string)($sort['dir'] ?? $sort['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
		return ['key' => $key, 'dir' => $dir];
	}

	/** @return array<int,string> */
	private function normalizeIds(mixed $values): array {
		if (is_string($values)) {
			$values = preg_split('/[\r\n,]+/', $values) ?: [];
		}
		if (!is_array($values)) {
			return [];
		}
		$result = [];
		foreach ($values as $value) {
			$value = $this->normalizeId((string)$value);
			if ($value !== '') {
				$result[$value] = true;
			}
		}
		return array_keys($result);
	}

	private function normalizeId(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}

	private function hasUsableFlow(mixed $value): bool {
		if (is_array($value)) {
			return $value !== [];
		}
		if (!is_string($value) || trim($value) === '') {
			return false;
		}
		$decoded = json_decode($value, true);
		return is_array($decoded) && $decoded !== [];
	}

	private function lower(string $value): string {
		return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
	}

	private function toBool(mixed $value): bool {
		if (is_bool($value)) {
			return $value;
		}
		if (is_int($value) || is_float($value)) {
			return $value !== 0;
		}
		return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
	}

	/** @return array<string,mixed> */
	private function error(string $message, string $mode): array {
		return ['ok' => false, 'mode' => $mode, 'error' => $message];
	}
}
