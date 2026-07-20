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
use MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use MissionBay\Orchestrator\Profile\AgentOrchestratorProfile;
use MissionBay\Orchestrator\Profile\AgentOrchestratorProfileRepository;
use Throwable;

/**
 * Admin UI for fixed-order orchestration profiles.
 *
 * Operators may choose a supported mode, limits and optional stages. Core
 * stages and their ordering are intentionally not editable.
 */
final class AgentOrchestratorProfileAdminDisplay implements IDisplay {

	private const BATCH_SIZE = 50;

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly IAssetResolver $assetResolver,
		private readonly ILinkTargetService $linkTargetService,
		private readonly AgentOrchestratorProfileRepository $profileRepository
	) {}

	public static function getName(): string {
		return 'agentorchestratorprofileadmindisplay';
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
		return 'Administrates MissionBay orchestrator profiles with a fixed canonical stage order.';
	}

	private function handleHtml(): string {
		$this->view->setPath(DIR_PLUGIN . 'MissionBay');
		$this->view->setTemplate('Display/AgentOrchestratorProfileAdminDisplay.php');
		$this->view->assign('service', $this->linkTargetService->getLink([
			'name' => self::getName(),
			'out' => 'json'
		]));
		$this->view->assign('model_decision_strategy_options', [
			['id' => AgentModelDecisionConfig::STRATEGY_AI_GUARDED, 'label' => 'AI-guarded model decision'],
			['id' => AgentModelDecisionConfig::STRATEGY_SIMPLE, 'label' => 'Simple model decision']
		]);
		$this->view->assign('mode_options', [
			['id' => AgentOrchestratorProfile::MODE_SIMPLE, 'label' => 'Simple tool agent'],
			['id' => AgentOrchestratorProfile::MODE_STANDARD, 'label' => 'Standard agent'],
			['id' => AgentOrchestratorProfile::MODE_DELIBERATE, 'label' => 'Deliberate evidence agent'],
			['id' => AgentOrchestratorProfile::MODE_GOVERNED, 'label' => 'Governed mutation agent']
		]);
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
				'error' => 'Orchestrator profile request failed.',
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
		$mode = strtolower(trim((string)($payload['mode'] ?? 'page')));

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
		$rows = array_values(array_map(
			fn(AgentOrchestratorProfile $profile): array => $this->normalizeRow($profile),
			$this->profileRepository->getProfiles()
		));
		$search = trim((string)($payload['search'] ?? ''));
		$filters = is_array($payload['filters'] ?? null) ? $payload['filters'] : [];

		if ($search !== '') {
			$needle = $this->lower($search);
			$rows = array_values(array_filter($rows, function(array $row) use ($needle): bool {
				return str_contains($this->lower(implode("\n", [
					(string)$row['profile_id'],
					(string)$row['label'],
					(string)$row['description'],
					(string)$row['mode'],
					(string)$row['model_decision_strategy'],
					(string)$row['stage_text']
				])), $needle);
			}));
		}

		if (($filters['mode'] ?? '') !== '') {
			$mode = strtolower(trim((string)$filters['mode']));
			$rows = array_values(array_filter($rows, static fn(array $row): bool => $row['mode'] === $mode));
		}
		if (($filters['enabled'] ?? '') !== '') {
			$enabled = $this->toBool($filters['enabled']);
			$rows = array_values(array_filter($rows, static fn(array $row): bool => $row['enabled'] === $enabled));
		}

		$sort = $this->normalizeSort($payload['sort'] ?? null);
		usort($rows, function(array $left, array $right) use ($sort): int {
			$key = $sort['key'];
			if ($key === 'max_tool_loops') {
				$result = ((int)$left[$key]) <=> ((int)$right[$key]);
			}
			else {
				$result = strcmp($this->lower((string)$left[$key]), $this->lower((string)$right[$key]));
			}
			if ($result === 0) {
				$result = strcmp((string)$left['profile_id'], (string)$right['profile_id']);
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
		$profiles = $this->profileRepository->getProfiles();

		if ($id === '' || !isset($profiles[$id])) {
			return $this->error('Orchestrator profile not found: ' . $id, 'record');
		}

		return [
			'ok' => true,
			'mode' => 'record',
			'record' => $this->normalizeRow($profiles[$id])
		];
	}

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	private function buildSaveResponse(array $payload): array {
		$oldId = $this->normalizeId((string)($payload['old_id'] ?? ''));
		$id = $this->normalizeId((string)($payload['id'] ?? ''));

		if ($id === '') {
			return $this->error('Profile ID must not be empty.', 'save');
		}
		if ($this->profileRepository->isBuiltin($id)) {
			return $this->error('Built-in profiles are read-only. Duplicate the profile first.', 'save');
		}
		if ($oldId !== '' && $oldId !== $id && $this->profileRepository->exists($id)) {
			return $this->error('Target profile already exists: ' . $id, 'save');
		}

		$capabilitySelectionEnabled = $this->toBool($payload['capability_selection'] ?? false);
		$aiCapabilitySelectionEnabled = $this->toBool($payload['ai_capability_selection'] ?? false);
		if ($capabilitySelectionEnabled && $aiCapabilitySelectionEnabled) {
			return $this->error('Capability selection stages are mutually exclusive.', 'save');
		}

		$settings = [
			'label' => trim((string)($payload['label'] ?? $id)),
			'description' => trim((string)($payload['description'] ?? '')),
			'enabled' => $this->toBool($payload['enabled'] ?? true),
			'mode' => strtolower(trim((string)($payload['profile_mode'] ?? AgentOrchestratorProfile::MODE_STANDARD))),
			'max_tool_loops' => max(1, min(100, (int)($payload['max_tool_loops'] ?? 10))),
			'model_decision' => [
				'strategy' => strtolower(trim((string)($payload['model_decision_strategy'] ?? AgentModelDecisionConfig::STRATEGY_AI_GUARDED))),
				'repair_enabled' => $this->toBool($payload['model_decision_repair_enabled'] ?? true),
				'confidence_threshold' => max(0.0, min(1.0, (float)($payload['model_decision_confidence_threshold'] ?? 0.7)))
			],
			'deliberate_planning' => $this->toBool($payload['deliberate_planning'] ?? false),
			'optional_stages' => [
				'capability-discovery' => $this->toBool($payload['capability_discovery'] ?? false),
				'capability-selection' => $capabilitySelectionEnabled,
				'ai-capability-selection' => $aiCapabilitySelectionEnabled,
				'context-compaction' => $this->toBool($payload['context_compaction'] ?? false),
				'semantic-verification' => $this->toBool($payload['semantic_verification'] ?? false)
			],
			'capability_selection' => [
				'enabled' => $capabilitySelectionEnabled || $aiCapabilitySelectionEnabled,
				'strategy' => $aiCapabilitySelectionEnabled
					? 'hybrid'
					: strtolower(trim((string)($payload['selection_strategy'] ?? 'hybrid'))),
				'max_tools' => max(1, min(512, (int)($payload['max_tools'] ?? 16))),
				'select_all_threshold' => max(0, min(512, (int)($payload['select_all_threshold'] ?? 16))),
				'semantic_candidate_tools' => max(1, min(512, (int)($payload['semantic_candidate_tools'] ?? 48))),
				'semantic_max_prompt_characters' => max(8000, min(200000, (int)($payload['semantic_max_prompt_characters'] ?? 48000))),
				'sticky' => $this->toBool($payload['sticky'] ?? true)
			]
		];

		try {
			$profile = $this->profileRepository->save($id, $settings);
			if ($oldId !== '' && $oldId !== $id && !$this->profileRepository->isBuiltin($oldId)) {
				$this->profileRepository->remove($oldId);
			}
		}
		catch (Throwable $e) {
			return $this->error($e->getMessage(), 'save');
		}

		return [
			'ok' => true,
			'mode' => 'save',
			'action' => $oldId !== '' && $oldId !== $id ? 'renamed and saved' : 'saved',
			'record' => $this->normalizeRow($profile)
		];
	}

	/** @return array<string,mixed> */
	private function buildDeleteResponse(string $id): array {
		$id = $this->normalizeId($id);
		if ($id === '') {
			return $this->error('Missing profile ID.', 'delete');
		}
		if ($this->profileRepository->isBuiltin($id)) {
			return $this->error('Built-in profiles cannot be deleted.', 'delete');
		}

		try {
			$this->profileRepository->remove($id);
		}
		catch (Throwable $e) {
			return $this->error($e->getMessage(), 'delete');
		}

		return ['ok' => true, 'mode' => 'delete', 'action' => 'deleted', 'id' => $id];
	}

	/** @return array<string,mixed> */
	private function buildReloadResponse(): array {
		try {
			$this->profileRepository->reload();
		}
		catch (Throwable $e) {
			return $this->error($e->getMessage(), 'reload');
		}

		return ['ok' => true, 'mode' => 'reload', 'action' => 'reloaded'];
	}

	/** @return array<string,mixed> */
	private function normalizeRow(AgentOrchestratorProfile $profile): array {
		$data = $profile->toArray();
		$stages = $profile->getStageIds();
		$optional = $profile->getOptionalStages();
		$selection = $profile->getCapabilitySelection()->toArray();
		$modelDecision = $profile->getModelDecision()->toArray();

		return array_merge($data, [
			'profile_id' => $profile->getId(),
			'old_id' => $profile->getId(),
			'enabled_label' => $profile->isEnabled() ? 'enabled' : 'disabled',
			'builtin_label' => $profile->isBuiltin() ? 'built-in' : 'custom',
			'stage_count' => count($stages),
			'stage_text' => implode(' → ', $stages),
			'capability_discovery' => (bool)($optional['capability-discovery'] ?? false),
			'capability_selection' => (bool)($optional['capability-selection'] ?? false),
			'ai_capability_selection' => (bool)($optional['ai-capability-selection'] ?? false),
			'selection_stage' => (bool)($optional['ai-capability-selection'] ?? false)
				? 'ai-capability-selection'
				: ((bool)($optional['capability-selection'] ?? false) ? 'capability-selection' : 'none'),
			'context_compaction' => (bool)($optional['context-compaction'] ?? false),
			'semantic_verification' => (bool)($optional['semantic-verification'] ?? false),
			'selection_strategy' => (string)($selection['strategy'] ?? 'hybrid'),
			'max_tools' => (int)($selection['max_tools'] ?? 16),
			'select_all_threshold' => (int)($selection['select_all_threshold'] ?? 16),
			'semantic_candidate_tools' => (int)($selection['semantic_candidate_tools'] ?? 48),
			'semantic_max_prompt_characters' => (int)($selection['semantic_max_prompt_characters'] ?? 48000),
			'sticky' => (bool)($selection['sticky'] ?? true),
			'model_decision_strategy' => (string)($modelDecision['strategy'] ?? AgentModelDecisionConfig::STRATEGY_AI_GUARDED),
			'model_decision_repair_enabled' => (bool)($modelDecision['repair_enabled'] ?? true),
			'model_decision_confidence_threshold' => (float)($modelDecision['confidence_threshold'] ?? 0.7),
			'deliberate_planning' => $profile->isDeliberatePlanningEnabled(),
			'profile_json' => $this->pretty($data)
		]);
	}

	/** @return array{key:string,dir:string,type:string} */
	private function normalizeSort(mixed $payload): array {
		$allowed = ['profile_id', 'label', 'mode', 'enabled_label', 'builtin_label', 'max_tool_loops', 'stage_count'];
		$first = is_array($payload) && $payload !== [] ? reset($payload) : null;
		$key = is_array($first) ? (string)($first['key'] ?? 'profile_id') : 'profile_id';
		$key = in_array($key, $allowed, true) ? $key : 'profile_id';
		$dir = is_array($first) && strtolower((string)($first['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

		return ['key' => $key, 'dir' => $dir, 'type' => in_array($key, ['max_tool_loops', 'stage_count'], true) ? 'int' : 'string'];
	}

	/** @return array<string,mixed> */
	private function error(string $message, string $mode): array {
		return ['ok' => false, 'mode' => $mode, 'error' => $message];
	}

	private function normalizeId(string $id): string {
		$id = strtolower(trim($id));
		return preg_replace('/[^a-z0-9._-]+/', '', $id) ?? '';
	}

	private function toBool(mixed $value): bool {
		if (is_bool($value)) return $value;
		if (is_int($value)) return $value !== 0;
		return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
	}

	private function lower(string $value): string {
		return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
	}

	/** @param array<string,mixed> $data */
	private function pretty(array $data): string {
		$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		return is_string($json) ? $json : '{}';
	}
}
