<?php declare(strict_types=1);

namespace MissionBay\Display;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentInstructionBlock;
use Base3\Api\IAssetResolver;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IOutputSchemaProvider;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use MissionBay\Api\IAgentComponentPresetMaterializer;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentTool;
use MissionBay\Dto\AgentComponentPresetMaterialization;
use MissionBay\Resource\ConfiguredAgentMemoryResource;
use MissionBay\Service\AgentComponentPresetToolTestService;

/**
 * Tests stored agent component presets through their materialized runtime capabilities.
 */
final class AgentComponentPresetTestAdminDisplay implements IDisplay {

	private const MEMORY_TEST_NODE_PREFIX = '__missionbay_test__';

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly IAssetResolver $assetResolver,
		private readonly ILinkTargetService $linkTargetService,
		private readonly IAgentComponentPresetRepository $presetRepository,
		private readonly IAgentComponentPresetMaterializer $materializer,
		private readonly AgentComponentPresetToolTestService $toolTestService
	) {}

	public static function getName(): string {
		return 'agentcomponentpresettestadmindisplay';
	}

	public function setData($data) {
		// no-op
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		if(strtolower($out) === 'json') {
			return $this->handleJson($final);
		}

		return $this->handleHtml();
	}

	private function handleHtml(): string {
		$this->view->setPath(DIR_PLUGIN . 'MissionBay');
		$this->view->setTemplate('Display/AgentComponentPresetTestAdminDisplay.php');
		$this->view->assign('service', $this->linkTargetService->getLink([
			'name' => self::getName(),
			'out' => 'json'
		]));
		$this->view->assign('resolve', fn($src) => $this->assetResolver->resolve((string)$src));
		$this->view->assign('memory_test_node_prefix', self::MEMORY_TEST_NODE_PREFIX);

		return $this->view->loadTemplate();
	}

	private function handleJson(bool $final): string {
		$response = $this->buildJsonResponse();

		if($final && !headers_sent()) {
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
		$request = $this->normalizeRequest($payload);

		try {
			return match($request['mode']) {
				'detail' => $this->buildDetailResponse($request['preset_id'], $request['context_vars']),
				'record' => $this->buildRecordResponse($request['preset_id']),
				'call_tool' => $this->buildToolCallResponse($request),
				'resume_tool' => $this->buildToolResumeResponse($request),
				'context_contribute' => $this->buildContextContributionResponse($request),
				'memory_load' => $this->buildMemoryLoadResponse($request),
				'memory_append' => $this->buildMemoryAppendResponse($request),
				'memory_feedback' => $this->buildMemoryFeedbackResponse($request),
				'memory_reset' => $this->buildMemoryResetResponse($request),
				default => $this->buildPageResponse($request)
			};
		}
		catch(\Throwable $e) {
			return [
				'mode' => $request['mode'],
				'ok' => false,
				'error' => $e->getMessage(),
				'exception' => $e::class
			];
		}
	}

	/** @param array<string,mixed> $request @return array<string,mixed> */
	private function buildPageResponse(array $request): array {
		$rows = $this->buildRows();
		$rows = $this->applySearch($rows, $request['search']);
		$rows = $this->applySort($rows, $request['sort']);
		$total = count($rows);
		$pageSize = $request['page_size'];
		$page = $request['page'];
		$totalPages = $pageSize > 0 ? (int)ceil($total / $pageSize) : 0;
		$offset = max(0, ($page - 1) * $pageSize);

		return [
			'mode' => 'page',
			'data' => array_slice($rows, $offset, $pageSize),
			'groups' => [],
			'page' => $page,
			'pageSize' => $pageSize,
			'total' => $total,
			'totalPages' => $totalPages,
			'hasMore' => ($offset + $pageSize) < $total,
			'nextCursor' => null,
			'appliedSearch' => $request['search'],
			'appliedSort' => [$request['sort']],
			'appliedFilters' => [],
			'appliedGroup' => []
		];
	}

	/** @return array<int,array<string,mixed>> */
	private function buildRows(): array {
		$rows = [];

		foreach($this->presetRepository->getPresets() as $id => $preset) {
			$id = trim((string)$id);
			$capabilities = $this->normalizeStringList($preset['capabilities'] ?? []);
			$label = trim((string)($preset['label'] ?? ''));
			$type = trim((string)($preset['type'] ?? ''));
			$enabled = $this->toBool($preset['enabled'] ?? true, true);
			$docks = is_array($preset['docks'] ?? null) ? $preset['docks'] : [];
			$meta = is_array($preset['meta'] ?? null) ? $preset['meta'] : [];
			$dockTargets = 0;

			foreach($docks as $targets) {
				$dockTargets += count((array)$targets);
			}

			$rows[] = [
				'id' => $id,
				'preset_id' => $id,
				'name' => $label !== '' ? $label : $id,
				'type' => $type,
				'enabled' => $enabled,
				'status' => $enabled ? ($type !== '' ? 'configured' : 'invalid') : 'disabled',
				'capabilities' => $capabilities,
				'capability_names' => implode(', ', $capabilities),
				'dock_count' => $dockTargets,
				'dock_names' => implode(', ', array_keys($docks)),
				'description' => trim((string)($meta['description'] ?? $preset['description'] ?? ''))
			];
		}

		return $rows;
	}

	/** @return array<string,mixed> */
	private function buildDetailResponse(string $presetId, array $contextVars): array {
		$context = $this->createContext($presetId, $contextVars);
		$materialization = $this->materializer->materialize($presetId, $context);
		$preset = $materialization->getPreset();
		$resource = $materialization->getResource();
		$tool = $materialization->getTool();
		$memory = $materialization->getMemory();
		$contributor = $materialization->getContextContributor();
		$definitions = $tool instanceof IAgentTool
			? $this->normalizeToolDefinitions($tool->getToolDefinitions())
			: [];
		$outputSchemas = $tool instanceof IOutputSchemaProvider ? $tool->getOutputSchemas() : [];

		foreach($definitions as &$definition) {
			$name = (string)($definition['name'] ?? '');
			if($name !== '' && isset($outputSchemas[$name]) && is_array($outputSchemas[$name])) {
				$definition['output_schema'] = $this->normalizeForJson($outputSchemas[$name]);
			}
		}
		unset($definition);

		$label = trim((string)($preset['label'] ?? ''));
		$meta = is_array($preset['meta'] ?? null) ? $preset['meta'] : [];
		$presetDescription = trim((string)($meta['description'] ?? $preset['description'] ?? ''));
		$resourceDescription = $resource instanceof IAgentResource ? trim($resource->getDescription()) : '';
		$headline = $label !== '' ? $label : $presetId;
		$badges = array_map(
			static fn(string $capability): string => ucfirst($capability),
			$materialization->getCapabilities()
		);

		if($resource instanceof IAgentResource) {
			$badges[] = $resource::getName();
		}

		return [
			'mode' => 'detail',
			'found' => $preset !== [],
			'detail' => [
				'id' => $presetId,
				'preset_id' => $presetId,
				'headline' => $headline,
				'summary' => $resource instanceof IAgentResource ? $resource::class : (string)($preset['type'] ?? ''),
				'description' => $presetDescription !== '' ? $presetDescription : $resourceDescription,
				'implementation_description' => $resourceDescription,
				'meta' => $this->normalizeForJson($meta),
				'ready' => $materialization->isReady(),
				'capabilities' => $materialization->getCapabilities(),
				'badges' => array_values(array_unique($badges)),
				'warnings' => $materialization->getWarnings(),
				'docks' => $materialization->getDocks(),
				'resource' => $resource instanceof IAgentResource ? [
					'id' => $resource->getId(),
					'name' => $resource::getName(),
					'class' => $resource::class,
					'config' => $this->normalizeForJson($resource->getConfig())
				] : null,
				'tool' => $tool instanceof IAgentTool ? [
					'class' => $tool::class,
					'function_count' => count($definitions),
					'functions' => $definitions,
					'definitions_json' => $this->encodePrettyJson($tool->getToolDefinitions())
				] : null,
				'context' => $contributor !== null ? [
					'class' => $contributor::class,
					'priority' => $contributor->getPriority()
				] : null,
				'memory' => $memory !== null ? [
					'class' => $memory::class,
					'priority' => $memory->getPriority(),
					'read_enabled' => !($memory instanceof ConfiguredAgentMemoryResource) || $memory->isReadEnabled(),
					'write_enabled' => !($memory instanceof ConfiguredAgentMemoryResource) || $memory->isWriteEnabled(),
					'test_node_prefix' => self::MEMORY_TEST_NODE_PREFIX
				] : null,
				'preset_json' => $this->encodePrettyJson($preset)
			]
		];
	}

	/** @return array<string,mixed> */
	private function buildRecordResponse(string $presetId): array {
		$preset = $this->presetRepository->getPreset($presetId, []);

		return [
			'mode' => 'record',
			'found' => $preset !== [],
			'record' => $preset !== [] ? $this->normalizeForJson($preset) : null
		];
	}

	/** @param array<string,mixed> $request @return array<string,mixed> */
	private function buildToolCallResponse(array $request): array {
		[$materialization, $context] = $this->materializeRequestPreset($request);
		$tool = $materialization->getTool();

		if(!$tool instanceof IAgentTool) {
			return $this->capabilityError('tool', $request['preset_id'], $materialization);
		}

		return array_replace([
			'mode' => 'call_tool',
			'preset_id' => $request['preset_id'],
			'function_name' => $request['function_name'],
			'arguments' => $this->normalizeForJson($request['arguments'])
		], $this->toolTestService->invoke(
			$tool,
			$request['function_name'],
			$request['arguments'],
			$context
		));
	}

	/** @param array<string,mixed> $request @return array<string,mixed> */
	private function buildToolResumeResponse(array $request): array {
		[$materialization, $context] = $this->materializeRequestPreset($request);
		$tool = $materialization->getTool();

		if(!$tool instanceof IAgentTool) {
			return $this->capabilityError('tool', $request['preset_id'], $materialization);
		}

		return array_replace([
			'mode' => 'resume_tool',
			'preset_id' => $request['preset_id']
		], $this->toolTestService->resume(
			$tool,
			$request['resume_handle'],
			$request['request_id'],
			$request['decision'],
			$request['note'],
			$context
		));
	}

	/** @param array<string,mixed> $request @return array<string,mixed> */
	private function buildContextContributionResponse(array $request): array {
		[$materialization, $context] = $this->materializeRequestPreset($request);
		$contributor = $materialization->getContextContributor();

		if($contributor === null) {
			return $this->capabilityError('context', $request['preset_id'], $materialization);
		}

		$blocks = [];

		foreach($contributor->contribute($context) as $block) {
			if(!$block instanceof AgentInstructionBlock) {
				throw new \RuntimeException('Context contributor returned an invalid instruction block type.');
			}
			$blocks[] = $block->toArray();
		}

		usort($blocks, static fn(array $left, array $right): int => ((int)$left['priority']) <=> ((int)$right['priority']));

		return [
			'mode' => 'context_contribute',
			'ok' => true,
			'preset_id' => $request['preset_id'],
			'priority' => $contributor->getPriority(),
			'blocks' => $blocks,
			'system_messages' => array_map(
				static fn(array $block): array => ['role' => 'system', 'content' => (string)$block['content']],
				$blocks
			),
			'warnings' => $materialization->getWarnings()
		];
	}

	/** @param array<string,mixed> $request @return array<string,mixed> */
	private function buildMemoryLoadResponse(array $request): array {
		[$materialization] = $this->materializeRequestPreset($request);
		$memory = $materialization->getMemory();

		if($memory === null) {
			return $this->capabilityError('memory', $request['preset_id'], $materialization);
		}

		return [
			'mode' => 'memory_load',
			'ok' => true,
			'preset_id' => $request['preset_id'],
			'node_id' => $request['node_id'],
			'history' => $this->normalizeForJson($memory->loadNodeHistory($request['node_id'])),
			'priority' => $memory->getPriority(),
			'warnings' => $materialization->getWarnings()
		];
	}

	/** @param array<string,mixed> $request @return array<string,mixed> */
	private function buildMemoryAppendResponse(array $request): array {
		$this->assertMemoryMutationRequest($request);
		[$materialization] = $this->materializeRequestPreset($request);
		$memory = $materialization->getMemory();

		if($memory === null) {
			return $this->capabilityError('memory', $request['preset_id'], $materialization);
		}

		$message = $request['message'];
		$message['id'] = trim((string)($message['id'] ?? '')) ?: uniqid('preset-test-message-', true);
		$message['role'] = trim((string)($message['role'] ?? 'user')) ?: 'user';
		$message['content'] = (string)($message['content'] ?? '');
		$message['timestamp'] = trim((string)($message['timestamp'] ?? '')) ?: gmdate('c');
		$message['feedback'] = array_key_exists('feedback', $message) ? $message['feedback'] : null;
		$memory->appendNodeHistory($request['node_id'], $message);

		return [
			'mode' => 'memory_append',
			'ok' => true,
			'preset_id' => $request['preset_id'],
			'node_id' => $request['node_id'],
			'message' => $this->normalizeForJson($message),
			'history' => $this->normalizeForJson($memory->loadNodeHistory($request['node_id']))
		];
	}

	/** @param array<string,mixed> $request @return array<string,mixed> */
	private function buildMemoryFeedbackResponse(array $request): array {
		$this->assertMemoryMutationRequest($request);
		[$materialization] = $this->materializeRequestPreset($request);
		$memory = $materialization->getMemory();

		if($memory === null) {
			return $this->capabilityError('memory', $request['preset_id'], $materialization);
		}

		$updated = $memory->setFeedback(
			$request['node_id'],
			$request['message_id'],
			$request['feedback'] !== '' ? $request['feedback'] : null
		);

		return [
			'mode' => 'memory_feedback',
			'ok' => $updated,
			'preset_id' => $request['preset_id'],
			'node_id' => $request['node_id'],
			'message_id' => $request['message_id'],
			'history' => $this->normalizeForJson($memory->loadNodeHistory($request['node_id']))
		];
	}

	/** @param array<string,mixed> $request @return array<string,mixed> */
	private function buildMemoryResetResponse(array $request): array {
		$this->assertMemoryMutationRequest($request);
		[$materialization] = $this->materializeRequestPreset($request);
		$memory = $materialization->getMemory();

		if($memory === null) {
			return $this->capabilityError('memory', $request['preset_id'], $materialization);
		}

		$memory->resetNodeHistory($request['node_id']);

		return [
			'mode' => 'memory_reset',
			'ok' => true,
			'preset_id' => $request['preset_id'],
			'node_id' => $request['node_id'],
			'history' => $this->normalizeForJson($memory->loadNodeHistory($request['node_id']))
		];
	}

	/** @param array<string,mixed> $request */
	private function assertMemoryMutationRequest(array $request): void {
		if($request['confirmed'] !== true) {
			throw new \RuntimeException('Memory mutation requires explicit confirmation.');
		}

		if(!str_starts_with($request['node_id'], self::MEMORY_TEST_NODE_PREFIX)) {
			throw new \RuntimeException(
				'Memory mutation is restricted to isolated test node ids beginning with ' . self::MEMORY_TEST_NODE_PREFIX
			);
		}
	}

	/**
	 * @param array<string,mixed> $request
	 * @return array{0:AgentComponentPresetMaterialization,1:IAgentContext}
	 */
	private function materializeRequestPreset(array $request): array {
		$context = $this->createContext($request['preset_id'], $request['context_vars']);
		return [$this->materializer->materialize($request['preset_id'], $context), $context];
	}

	/** @param array<string,mixed> $contextVars */
	private function createContext(string $presetId, array $contextVars): IAgentContext {
		return $this->materializer->createContext(array_replace($contextVars, [
			'source' => 'agent-component-preset-test',
			'component_preset_id' => $presetId
		]));
	}

	/** @return array<string,mixed> */
	private function capabilityError(
		string $capability,
		string $presetId,
		AgentComponentPresetMaterialization $materialization
	): array {
		return [
			'ok' => false,
			'error' => 'Materialized preset does not expose the ' . $capability . ' capability: ' . $presetId,
			'warnings' => $materialization->getWarnings()
		];
	}

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	private function normalizeRequest(array $payload): array {
		$allowedModes = [
			'page', 'detail', 'record', 'call_tool', 'resume_tool', 'context_contribute',
			'memory_load', 'memory_append', 'memory_feedback', 'memory_reset'
		];
		$mode = strtolower(trim((string)($payload['mode'] ?? 'page')));
		$mode = in_array($mode, $allowedModes, true) ? $mode : 'page';
		$page = max(1, (int)($payload['page'] ?? 1));
		$pageSize = max(1, min(200, (int)($payload['pageSize'] ?? 50)));

		return [
			'mode' => $mode,
			'page' => $page,
			'page_size' => $pageSize,
			'search' => trim((string)($payload['search'] ?? '')),
			'sort' => $this->normalizeSort($payload['sort'] ?? null),
			'preset_id' => trim((string)($payload['preset_id'] ?? $payload['id'] ?? '')),
			'function_name' => trim((string)($payload['function_name'] ?? '')),
			'arguments' => $this->normalizeArray($payload['arguments'] ?? []),
			'context_vars' => $this->normalizeArray($payload['context_vars'] ?? []),
			'resume_handle' => trim((string)($payload['resume_handle'] ?? '')),
			'request_id' => trim((string)($payload['request_id'] ?? '')),
			'decision' => strtolower(trim((string)($payload['decision'] ?? ''))),
			'note' => trim((string)($payload['note'] ?? '')),
			'node_id' => trim((string)($payload['node_id'] ?? '')),
			'message_id' => trim((string)($payload['message_id'] ?? '')),
			'feedback' => trim((string)($payload['feedback'] ?? '')),
			'message' => $this->normalizeArray($payload['message'] ?? []),
			'confirmed' => $this->toBool($payload['confirmed'] ?? false, false)
		];
	}

	/** @return array<string,string> */
	private function normalizeSort(mixed $value): array {
		$allowed = ['name', 'type', 'status', 'capability_names', 'dock_count'];
		$first = is_array($value) ? reset($value) : null;
		$key = is_array($first) ? trim((string)($first['key'] ?? 'name')) : 'name';
		$key = in_array($key, $allowed, true) ? $key : 'name';
		$dir = is_array($first) && strtolower((string)($first['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

		return [
			'key' => $key,
			'dir' => $dir,
			'type' => $key === 'dock_count' ? 'int' : 'string'
		];
	}

	/** @return array<string,mixed> */
	private function normalizeArray(mixed $value): array {
		if($value instanceof \stdClass) {
			$value = (array)$value;
		}
		return is_array($value) ? $value : [];
	}

	/** @return array<int,array<string,mixed>> */
	private function normalizeToolDefinitions(mixed $definitions): array {
		$result = [];

		foreach(is_array($definitions) ? $definitions : [] as $definition) {
			if(!is_array($definition)) {
				continue;
			}
			$function = is_array($definition['function'] ?? null) ? $definition['function'] : [];
			$name = trim((string)($function['name'] ?? $definition['name'] ?? ''));

			if($name === '') {
				continue;
			}

			$parameters = is_array($function['parameters'] ?? null) ? $function['parameters'] : [];
			$properties = is_array($parameters['properties'] ?? null) ? $parameters['properties'] : [];
			$requiredParameters = array_values(array_filter(
				is_array($parameters['required'] ?? null) ? $parameters['required'] : [],
				static fn(mixed $parameter): bool => is_scalar($parameter) && trim((string)$parameter) !== ''
			));
			$annotations = is_array($function['annotations'] ?? null) ? $function['annotations'] : [];

			$result[] = [
				'name' => $name,
				'label' => trim((string)($definition['label'] ?? $name)),
				'description' => trim((string)($function['description'] ?? '')),
				'category' => trim((string)($definition['category'] ?? '')),
				'tags' => array_values(is_array($definition['tags'] ?? null) ? $definition['tags'] : []),
				'priority' => (int)($definition['priority'] ?? 0),
				'parameters' => $this->normalizeForJson($parameters),
				'parameter_count' => count($properties),
				'required_parameters' => array_map(static fn(mixed $parameter): string => (string)$parameter, $requiredParameters),
				'read_only' => $this->toBool($definition['readOnlyHint'] ?? $annotations['readOnlyHint'] ?? false, false),
				'mutation' => $this->toBool($definition['mutation'] ?? $annotations['mutation'] ?? false, false),
				'requires_approval' => $this->toBool($definition['requiresApproval'] ?? $annotations['requiresApproval'] ?? false, false),
				'commit_guard_required' => $this->toBool($definition['commitGuardRequired'] ?? $annotations['commitGuardRequired'] ?? false, false),
				'side_effect' => $this->toBool($definition['sideEffectHint'] ?? $annotations['sideEffectHint'] ?? false, false),
				'annotations' => $this->normalizeForJson($annotations),
				'raw' => $this->normalizeForJson($definition)
			];
		}

		usort($result, static function(array $left, array $right): int {
			$priority = ((int)$right['priority']) <=> ((int)$left['priority']);
			return $priority !== 0 ? $priority : strcasecmp((string)$left['name'], (string)$right['name']);
		});

		return $result;
	}

	/** @param array<int,array<string,mixed>> $rows @return array<int,array<string,mixed>> */
	private function applySearch(array $rows, string $search): array {
		if($search === '') {
			return $rows;
		}
		$needle = $this->toLower($search);

		return array_values(array_filter($rows, function(array $row) use ($needle): bool {
			$haystack = implode("\n", [
				(string)$row['preset_id'],
				(string)$row['name'],
				(string)$row['type'],
				(string)$row['status'],
				(string)$row['capability_names'],
				(string)$row['dock_names'],
				(string)$row['description']
			]);
			return str_contains($this->toLower($haystack), $needle);
		}));
	}

	/** @param array<int,array<string,mixed>> $rows @param array<string,string> $sort @return array<int,array<string,mixed>> */
	private function applySort(array $rows, array $sort): array {
		$key = $sort['key'];
		$direction = $sort['dir'];

		usort($rows, function(array $left, array $right) use ($key, $direction): int {
			$result = $key === 'dock_count'
				? ((int)$left[$key]) <=> ((int)$right[$key])
				: strcasecmp((string)$left[$key], (string)$right[$key]);
			if($result === 0) {
				$result = strcasecmp((string)$left['name'], (string)$right['name']);
			}
			return $direction === 'desc' ? -$result : $result;
		});

		return $rows;
	}

	/** @return array<int,string> */
	private function normalizeStringList(mixed $value): array {
		if(is_string($value)) {
			$value = explode(',', $value);
		}
		$result = [];
		foreach(is_array($value) ? $value : [] as $item) {
			$item = strtolower(trim((string)$item));
			if($item !== '') {
				$result[] = $item;
			}
		}
		$result = array_values(array_unique($result));
		sort($result);
		return $result;
	}

	private function toBool(mixed $value, bool $default): bool {
		if($value === null || $value === '') return $default;
		if(is_bool($value)) return $value;
		if(is_int($value)) return $value !== 0;
		$value = strtolower(trim((string)$value));
		if(in_array($value, ['1', 'true', 'yes', 'on'], true)) return true;
		if(in_array($value, ['0', 'false', 'no', 'off'], true)) return false;
		return $default;
	}

	private function normalizeForJson(mixed $value, int $depth = 0): mixed {
		if($depth > 20) return '[max-depth]';
		if($value === null || is_scalar($value)) return $value;
		if(is_array($value)) {
			$result = [];
			foreach($value as $key => $item) {
				$result[$key] = $this->normalizeForJson($item, $depth + 1);
			}
			return $result;
		}
		if($value instanceof \JsonSerializable) return $this->normalizeForJson($value->jsonSerialize(), $depth + 1);
		if($value instanceof \stdClass) return $this->normalizeForJson((array)$value, $depth + 1);
		if(is_object($value)) {
			$result = ['__class' => $value::class];
			if(method_exists($value, 'toArray')) {
				$result['data'] = $this->normalizeForJson($value->toArray(), $depth + 1);
			}
			elseif(method_exists($value, '__toString')) {
				$result['__string'] = (string)$value;
			}
			return $result;
		}
		return is_resource($value) ? '[resource]' : '[unsupported]';
	}

	private function encodePrettyJson(mixed $value): string {
		$json = json_encode(
			$this->normalizeForJson($value),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
		);
		return is_string($json) ? $json : 'null';
	}

	private function toLower(string $value): string {
		return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
	}
}
