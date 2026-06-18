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

namespace MissionBay\Resource;

use Base3\Api\ISchemaProvider;
use Base3\Logger\Api\ILogger;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentKnowledgeService;
use MissionBay\Api\IAgentMemory;
use MissionBay\Api\IAgentTool;

class KnowledgeAgentResource extends AbstractAgentResource implements IAgentMemory, IAgentTool, ISchemaProvider {

	private const SYSTEM_TITLE = 'Knowledge memory';

	private ?ILogger $logger = null;

	private int $priority = 30;
	private int $injectLimit = 8;
	private int $injectMaxLength = 2500;
	private int $injectFetchLimit = 40;
	private bool $injectOnlyAlways = true;

	/**
	 * @var array<int,string>
	 */
	private array $injectTypes = ['task', 'episodic', 'semantic', 'procedural'];

	public function __construct(
		private readonly IAgentKnowledgeService $knowledge,
		private readonly IAgentConfigValueResolver $resolver,
		?string $id = null
	) {
		parent::__construct($id);
	}

	public static function getName(): string {
		return 'knowledgeagentresource';
	}

	public function getDescription(): string {
		return 'Provides persistent knowledge memory as both tool access and system prompt injection.';
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getSchema(): array {
		return [
			'$schema' => 'https://json-schema.org/draft-2020-12/schema',
			'type' => 'object',
			'properties' => [
				'priority' => [
					'type' => 'integer',
					'description' => 'Memory priority used when multiple memories are attached to an assistant node. Lower values are loaded first.',
					'default' => 30
				],
				'injectlimit' => [
					'type' => 'integer',
					'description' => 'Maximum number of knowledge entries injected as system memory.',
					'default' => 8,
					'minimum' => 1
				],
				'injectmaxlength' => [
					'type' => 'integer',
					'description' => 'Maximum total character budget for injected knowledge lines. Values less than or equal to 0 disable this limit.',
					'default' => 2500,
					'minimum' => 0
				],
				'injectfetchlimit' => [
					'type' => 'integer',
					'description' => 'Maximum number of candidate entries fetched before injection filtering and sorting.',
					'default' => 40,
					'minimum' => 1
				],
				'injectonlyalways' => [
					'type' => 'boolean',
					'description' => 'If true, only entries marked always_inject or pinned are considered for automatic system prompt injection.',
					'default' => true
				],
				'injecttypes' => [
					'type' => 'array',
					'description' => 'Knowledge memory types considered for automatic system prompt injection.',
					'items' => [
						'type' => 'string',
						'enum' => ['task', 'episodic', 'semantic', 'procedural']
					],
					'default' => ['task', 'episodic', 'semantic', 'procedural'],
					'uniqueItems' => true
				]
			],
			'required' => []
		];
	}

	/**
	 * @return AgentNodeDock[]
	 */
	public function getDockDefinitions(): array {
		return [
			new AgentNodeDock(
				name: 'logger',
				description: 'Optional logger for knowledge memory events.',
				interface: ILogger::class,
				maxConnections: 1,
				required: false
			)
		];
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->priority = (int)($this->resolver->resolveValue($config['priority'] ?? null) ?? 30);
		$this->injectLimit = (int)($this->resolver->resolveValue($config['injectlimit'] ?? null) ?? 8);
		$this->injectMaxLength = (int)($this->resolver->resolveValue($config['injectmaxlength'] ?? null) ?? 2500);
		$this->injectFetchLimit = (int)($this->resolver->resolveValue($config['injectfetchlimit'] ?? null) ?? 40);
		$this->injectOnlyAlways = $this->toBool($this->resolver->resolveValue($config['injectonlyalways'] ?? null) ?? true);

		$injectTypes = $this->resolver->resolveValue($config['injecttypes'] ?? null);
		$this->injectTypes = $this->normalizeStringArray($injectTypes);

		if(!$this->injectTypes) {
			$this->injectTypes = ['task', 'episodic', 'semantic', 'procedural'];
		}
	}

	public function init(array $resources, IAgentContext $context): void {
		if(!empty($resources['logger'][0]) && $resources['logger'][0] instanceof ILogger) {
			$this->logger = $resources['logger'][0];
			$this->log('logger docked into KnowledgeAgentResource');
		}
	}

	// ----------------------------------------------------
	// IAgentMemory
	// ----------------------------------------------------

	public function loadNodeHistory(string $nodeId): array {
		$lines = $this->buildSystemLines();

		if(!$lines) {
			return [];
		}

		$content = self::SYSTEM_TITLE . ":\n- " . implode("\n- ", $lines);

		return [[
			'role' => 'system',
			'content' => $content
		]];
	}

	public function appendNodeHistory(string $nodeId, array $message): void {
		// no-op
	}

	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
		// no-op
		return false;
	}

	public function resetNodeHistory(string $nodeId): void {
		// no-op
	}

	public function getPriority(): int {
		return $this->priority;
	}

	// ----------------------------------------------------
	// IAgentTool
	// ----------------------------------------------------

	public function getToolDefinitions(): array {
		return [
			$this->buildListTypesDefinition(),
			$this->buildListStatusesDefinition(),
			$this->buildGetEntryDefinition(),
			$this->buildDeleteEntryDefinition(),
			$this->buildGenericSearchDefinition(),

			$this->buildCreateDefinition(
				'task_memory_create',
				'Create Task Memory',
				'Create a task memory entry for goals, progress, blockers, open work, or active state. Use this when the assistant should remember the current state of a task for later turns.'
			),
			$this->buildUpdateDefinition(
				'task_memory_update',
				'Update Task Memory',
				'Update an existing task memory entry. Use this when task status, progress, blockers, or conclusions changed and the stored state should stay accurate.'
			),
			$this->buildUpsertDefinition(
				'task_memory_upsert',
				'Upsert Task Memory',
				'Create or update a task memory entry. Use this when the system should keep one canonical task record and update it safely instead of creating duplicates.'
			),
			$this->buildTypedSearchDefinition(
				'task_memory_search',
				'Search Task Memory',
				'Search task memory for goals, current progress, blockers, constraints, or open work. Prefer this before answering questions about ongoing work or task state.'
			),

			$this->buildCreateDefinition(
				'episodic_memory_create',
				'Create Episodic Memory',
				'Create an episodic memory entry for a concrete past case, incident, decision, interaction, or lesson learned. Use this to remember a specific past event.'
			),
			$this->buildUpdateDefinition(
				'episodic_memory_update',
				'Update Episodic Memory',
				'Update an existing episodic memory entry. Use this when a remembered case evolves, receives a resolution, or should be enriched with new observations.'
			),
			$this->buildUpsertDefinition(
				'episodic_memory_upsert',
				'Upsert Episodic Memory',
				'Create or update an episodic memory entry. Use this when the same case should be kept as one evolving canonical record instead of multiple fragmented notes.'
			),
			$this->buildTypedSearchDefinition(
				'episodic_memory_search',
				'Search Episodic Memory',
				'Search episodic memory for concrete past cases, incidents, decisions, and lessons learned. Prefer this when a similar past situation may help with the current request.'
			),

			$this->buildCreateDefinition(
				'semantic_memory_create',
				'Create Semantic Memory',
				'Create a semantic memory entry for stable facts, rules, definitions, mappings, or domain truths. Use this to store reliable general knowledge rather than a one-off case. For user preferences, response style, and persistent personal rules, prefer memory_subtype user_preference, always_inject true, and a stable memory_key.'
			),
			$this->buildUpdateDefinition(
				'semantic_memory_update',
				'Update Semantic Memory',
				'Update an existing semantic memory entry. Use this when a fact, rule, definition, or known truth changed, became deprecated, or needs correction.'
			),
			$this->buildUpsertDefinition(
				'semantic_memory_upsert',
				'Upsert Semantic Memory',
				'Create or update a semantic memory entry. Use this when stable knowledge should remain consolidated under one reusable canonical record. For user preferences, response style, and persistent rules, prefer upsert with memory_subtype user_preference, always_inject true, and a stable memory_key.'
			),
			$this->buildTypedSearchDefinition(
				'semantic_memory_search',
				'Search Semantic Memory',
				'Search semantic memory for stable facts, definitions, rules, policies, or known truths. Prefer this before answering factual or rule-based questions.'
			),

			$this->buildCreateDefinition(
				'procedural_memory_create',
				'Create Procedural Memory',
				'Create a procedural memory entry for workflows, checklists, SOPs, commands, skills, or step-by-step methods. Use this to store reusable know-how.'
			),
			$this->buildUpdateDefinition(
				'procedural_memory_update',
				'Update Procedural Memory',
				'Update an existing procedural memory entry. Use this when a workflow, SOP, checklist, or reusable method changed and the stored guidance should stay current.'
			),
			$this->buildUpsertDefinition(
				'procedural_memory_upsert',
				'Upsert Procedural Memory',
				'Create or update a procedural memory entry. Use this when the same workflow or skill should stay consolidated as one canonical procedure.'
			),
			$this->buildTypedSearchDefinition(
				'procedural_memory_search',
				'Search Procedural Memory',
				'Search procedural memory for workflows, SOPs, playbooks, and step-by-step methods. Prefer this when you need to know how something should be done.'
			),
			$this->buildProceduralApplicableDefinition(),
		];
	}

	public function callTool(string $toolName, array $arguments, IAgentContext $context): mixed {
		return match ($toolName) {
			'knowledge_memory_list_types' => $this->toolListTypes(),
			'knowledge_memory_list_statuses' => $this->toolListStatuses($arguments),
			'knowledge_memory_get_entry' => $this->toolGetEntry($arguments),
			'knowledge_memory_delete_entry' => $this->toolDeleteEntry($arguments),
			'knowledge_memory_search' => $this->toolSearchMemory(null, $arguments),

			'task_memory_create' => $this->toolCreateMemory('task', $arguments),
			'task_memory_update' => $this->toolUpdateMemory('task', $arguments),
			'task_memory_upsert' => $this->toolUpsertMemory('task', $arguments),
			'task_memory_search' => $this->toolSearchMemory('task', $arguments),

			'episodic_memory_create' => $this->toolCreateMemory('episodic', $arguments),
			'episodic_memory_update' => $this->toolUpdateMemory('episodic', $arguments),
			'episodic_memory_upsert' => $this->toolUpsertMemory('episodic', $arguments),
			'episodic_memory_search' => $this->toolSearchMemory('episodic', $arguments),

			'semantic_memory_create' => $this->toolCreateMemory('semantic', $arguments),
			'semantic_memory_update' => $this->toolUpdateMemory('semantic', $arguments),
			'semantic_memory_upsert' => $this->toolUpsertMemory('semantic', $arguments),
			'semantic_memory_search' => $this->toolSearchMemory('semantic', $arguments),

			'procedural_memory_create' => $this->toolCreateMemory('procedural', $arguments),
			'procedural_memory_update' => $this->toolUpdateMemory('procedural', $arguments),
			'procedural_memory_upsert' => $this->toolUpsertMemory('procedural', $arguments),
			'procedural_memory_search' => $this->toolSearchMemory('procedural', $arguments),
			'procedural_memory_get_applicable' => $this->toolProceduralGetApplicable($arguments),

			default => throw new \InvalidArgumentException("Unsupported tool: $toolName")
		};
	}

	// ----------------------------------------------------
	// Tool handlers
	// ----------------------------------------------------

	private function toolListTypes(): array {
		$types = $this->knowledge->getMemoryTypes();

		$this->log('tool knowledge_memory_list_types => ' . count($types));

		return [
			'count' => count($types),
			'types' => $types
		];
	}

	private function toolListStatuses(array $arguments): array {
		$memoryType = trim((string)($arguments['memory_type'] ?? ''));

		if($memoryType !== '') {
			$statuses = $this->knowledge->getAllowedStatuses($memoryType);

			$this->log('tool knowledge_memory_list_statuses type=' . $memoryType . ' => ' . count($statuses));

			return [
				'memory_type' => $memoryType,
				'count' => count($statuses),
				'statuses' => $statuses
			];
		}

		$out = [];
		foreach($this->knowledge->getMemoryTypes() as $type) {
			$out[$type] = $this->knowledge->getAllowedStatuses($type);
		}

		$this->log('tool knowledge_memory_list_statuses => all');

		return [
			'count' => count($out),
			'statuses_by_type' => $out
		];
	}

	private function toolGetEntry(array $arguments): array {
		$id = (int)($arguments['id'] ?? 0);

		if($id <= 0) {
			return ['error' => 'Missing or invalid parameter: id'];
		}

		$entry = $this->knowledge->getEntryById($id, false);

		if(!$entry) {
			return ['error' => 'Knowledge entry not found'];
		}

		$this->knowledge->touchEntry($id);
		$this->log('tool knowledge_memory_get_entry id=' . $id);

		return [
			'ok' => true,
			'entry' => $entry
		];
	}

	private function toolDeleteEntry(array $arguments): array {
		$id = (int)($arguments['id'] ?? 0);

		if($id <= 0) {
			return ['error' => 'Missing or invalid parameter: id'];
		}

		$deleted = $this->knowledge->deleteEntry($id, 'llm');

		$this->log('tool knowledge_memory_delete_entry id=' . $id . ' deleted=' . ($deleted ? 'yes' : 'no'));

		return [
			'ok' => true,
			'id' => $id,
			'deleted' => $deleted
		];
	}

	private function toolSearchMemory(?string $fixedType, array $arguments): array {
		$query = trim((string)($arguments['query'] ?? ''));
		$memoryType = $fixedType ?: trim((string)($arguments['memory_type'] ?? ''));
		$limit = (int)($arguments['limit'] ?? 10);
		$limit = max(1, min(50, $limit));

		$options = [];

		if($memoryType !== '') {
			$options['memory_type'] = $memoryType;
		}
		if(!empty($arguments['status'])) {
			$options['status'] = trim((string)$arguments['status']);
		}
		if(array_key_exists('scope_ref', $arguments)) {
			$options['scope_ref'] = $arguments['scope_ref'];
		}
		if(!empty($arguments['tags'])) {
			$options['tags'] = $this->normalizeStringArray($arguments['tags']);
		}
		if(!empty($arguments['entity_refs'])) {
			$options['entity_refs'] = $this->normalizeStringArray($arguments['entity_refs']);
		}
		if(array_key_exists('not_expired', $arguments)) {
			$options['not_expired'] = $this->toBool($arguments['not_expired']);
		}
		else {
			$options['not_expired'] = true;
		}

		$entries = $this->knowledge->searchEntries($query, $options, $limit, 0);

		foreach($entries as $entry) {
			if(!empty($entry['id'])) {
				$this->knowledge->touchEntry((int)$entry['id']);
			}
		}

		$this->log('tool ' . ($fixedType ? $fixedType . '_memory_search' : 'knowledge_memory_search') . ' query=' . ($query !== '' ? $query : '[empty]') . ' count=' . count($entries));

		return [
			'ok' => true,
			'query' => $query,
			'memory_type' => $memoryType !== '' ? $memoryType : null,
			'count' => count($entries),
			'entries' => $entries
		];
	}

	private function toolCreateMemory(string $memoryType, array $arguments): array {
		$title = trim((string)($arguments['title'] ?? ''));
		$content = trim((string)($arguments['content'] ?? ''));

		$this->log('tool ' . $memoryType . '_memory_create start title=' . $title);

		if($title === '') {
			$this->log('tool ' . $memoryType . '_memory_create error missing title');
			return ['error' => 'Missing parameter: title'];
		}
		if($content === '') {
			$this->log('tool ' . $memoryType . '_memory_create error missing content');
			return ['error' => 'Missing parameter: content'];
		}

		try {
			$data = $this->buildEntryDataFromArguments($arguments, true, null);
			$data['memory_type'] = $memoryType;

			if(empty($data['status'])) {
				$data['status'] = $this->getDefaultStatusForType($memoryType);
			}

			$id = $this->knowledge->createEntry($data);
			$entry = $id > 0 ? $this->knowledge->getEntryById($id, false) : null;

			$this->log('tool ' . $memoryType . '_memory_create success id=' . $id);

			return [
				'ok' => true,
				'action' => 'created',
				'id' => $id > 0 ? $id : null,
				'entry' => $entry
			];
		}
		catch(\Throwable $e) {
			$this->log('tool ' . $memoryType . '_memory_create exception=' . $e->getMessage());

			return [
				'error' => 'Failed to create knowledge entry',
				'details' => $e->getMessage()
			];
		}
	}

	private function injectWriteToken(array $arguments, string $writeToken): array {
		$meta = [];

		if(isset($arguments['meta']) && is_array($arguments['meta'])) {
			$meta = $arguments['meta'];
		}

		$meta['_write_token'] = $writeToken;
		$arguments['meta'] = $meta;

		return $arguments;
	}

	private function findCreatedEntryByWriteToken(string $memoryType, string $writeToken): ?array {
		$entries = $this->knowledge->findEntries(
			[
				'memory_type' => $memoryType
			],
			50,
			0
		);

		foreach($entries as $entry) {
			$meta = is_array($entry['meta_json'] ?? null) ? $entry['meta_json'] : [];

			if(($meta['_write_token'] ?? null) === $writeToken) {
				return $entry;
			}
		}

		return null;
	}

	private function toolUpdateMemory(string $memoryType, array $arguments): array {
		$id = (int)($arguments['id'] ?? 0);

		if($id <= 0) {
			return ['error' => 'Missing or invalid parameter: id'];
		}

		$existing = $this->knowledge->getEntryById($id, false);

		if(!$existing) {
			return ['error' => 'Knowledge entry not found'];
		}

		if((string)($existing['memory_type'] ?? '') !== $memoryType) {
			return ['error' => 'Knowledge entry does not match required memory type'];
		}

		$data = $this->buildEntryDataFromArguments($arguments, false, $existing);
		$data['updated_by'] = (string)($data['updated_by'] ?? 'llm');

		$updated = $this->knowledge->updateEntry($id, $data);
		$entry = $this->knowledge->getEntryById($id, false);

		$this->log('tool ' . $memoryType . '_memory_update id=' . $id . ' updated=' . ($updated ? 'yes' : 'no'));

		return [
			'ok' => true,
			'action' => 'updated',
			'id' => $id,
			'updated' => $updated,
			'entry' => $entry
		];
	}

	private function toolUpsertMemory(string $memoryType, array $arguments): array {
		$title = trim((string)($arguments['title'] ?? ''));

		if($title === '') {
			return ['error' => 'Missing parameter: title'];
		}

		$existing = $this->findExistingEntryForUpsert($memoryType, $arguments);

		if(!$existing) {
			if(trim((string)($arguments['content'] ?? '')) === '') {
				return ['error' => 'Missing parameter: content (required when no existing entry is found)'];
			}

			$result = $this->toolCreateMemory($memoryType, $arguments);
			$result['action'] = 'created';

			$this->log('tool ' . $memoryType . '_memory_upsert => created');
			return $result;
		}

		$updateArgs = $arguments;
		$updateArgs['id'] = (int)$existing['id'];

		$result = $this->toolUpdateMemory($memoryType, $updateArgs);
		$result['action'] = 'updated';
		$result['matched_entry_id'] = (int)$existing['id'];

		$this->log('tool ' . $memoryType . '_memory_upsert => updated id=' . (int)$existing['id']);

		return $result;
	}

	private function toolProceduralGetApplicable(array $arguments): array {
		$query = trim((string)($arguments['query'] ?? ''));

		if($query === '') {
			return ['error' => 'Missing parameter: query'];
		}

		$limit = (int)($arguments['limit'] ?? 5);
		$limit = max(1, min(20, $limit));

		$options = [
			'memory_type' => 'procedural',
			'status' => 'active',
			'not_expired' => true
		];

		if(array_key_exists('scope_ref', $arguments)) {
			$options['scope_ref'] = $arguments['scope_ref'];
		}
		if(!empty($arguments['tags'])) {
			$options['tags'] = $this->normalizeStringArray($arguments['tags']);
		}
		if(!empty($arguments['entity_refs'])) {
			$options['entity_refs'] = $this->normalizeStringArray($arguments['entity_refs']);
		}

		$entries = $this->knowledge->searchEntries($query, $options, $limit, 0);

		foreach($entries as $entry) {
			if(!empty($entry['id'])) {
				$this->knowledge->touchEntry((int)$entry['id']);
			}
		}

		$this->log('tool procedural_memory_get_applicable query=' . $query . ' count=' . count($entries));

		return [
			'ok' => true,
			'query' => $query,
			'count' => count($entries),
			'applicable' => $entries
		];
	}

	// ----------------------------------------------------
	// System text generation
	// ----------------------------------------------------

	private function buildSystemLines(): array {
		$entries = $this->loadCuratedInjectEntries();

		if(!$entries) {
			return [];
		}

		$filtered = [];

		foreach($entries as $entry) {
			if(!$this->isInjectableEntry($entry)) {
				continue;
			}

			if(!$this->knowledge->isEntryValidAt($entry, null)) {
				continue;
			}

			$filtered[] = $entry;
		}

		if(!$filtered) {
			return [];
		}

		usort($filtered, function(array $a, array $b): int {
			$aOrder = (int)($a['meta_json']['inject_order'] ?? 100);
			$bOrder = (int)($b['meta_json']['inject_order'] ?? 100);

			if($aOrder !== $bOrder) {
				return $aOrder <=> $bOrder;
			}

			$aPriority = (int)($a['priority'] ?? 0);
			$bPriority = (int)($b['priority'] ?? 0);

			if($aPriority !== $bPriority) {
				return $bPriority <=> $aPriority;
			}

			$aUpdated = strtotime((string)($a['updated_at'] ?? '')) ?: 0;
			$bUpdated = strtotime((string)($b['updated_at'] ?? '')) ?: 0;

			return $bUpdated <=> $aUpdated;
		});

		$filtered = array_slice($filtered, 0, max(1, $this->injectLimit));

		$lines = [];
		$totalLen = 0;

		foreach($filtered as $entry) {
			$line = $this->renderSystemLine($entry);

			if($line === '') {
				continue;
			}

			$lineLen = function_exists('mb_strlen') ? mb_strlen($line) : strlen($line);

			if($this->injectMaxLength > 0 && $totalLen > 0 && ($totalLen + $lineLen + 3) > $this->injectMaxLength) {
				break;
			}

			$lines[] = $line;
			$totalLen += $lineLen + 3;

			if(!empty($entry['id'])) {
				$this->knowledge->touchEntry((int)$entry['id']);
			}
		}

		return $lines;
	}

	private function loadCuratedInjectEntries(): array {
		$options = [
			'memory_type' => $this->injectTypes,
			'not_expired' => true,
		];

		if($this->injectOnlyAlways) {
			$options['always_inject'] = true;
		}

		if(method_exists($this->knowledge, 'loadCuratedEntries')) {
			/** @var array<int,array<string,mixed>> $entries */
			$entries = $this->knowledge->loadCuratedEntries($options, $this->injectFetchLimit, 0);
			return $entries;
		}

		return $this->knowledge->searchEntries('', ['not_expired' => true], $this->injectFetchLimit, 0);
	}

	private function renderSystemLine(array $entry): string {
		$type = ucfirst((string)($entry['memory_type'] ?? 'knowledge'));
		$title = trim((string)($entry['title'] ?? ''));
		$summary = trim((string)($entry['summary'] ?? ''));

		if($summary === '') {
			$summary = trim((string)($entry['content'] ?? ''));
		}

		$summary = $this->truncate($summary, 300);

		if($summary === '') {
			return '';
		}

		if($title !== '') {
			return $type . ' - ' . $title . ': ' . $summary;
		}

		return $type . ': ' . $summary;
	}

	private function isInjectableEntry(array $entry): bool {
		$memoryType = (string)($entry['memory_type'] ?? '');

		if(!in_array($memoryType, $this->injectTypes, true)) {
			return false;
		}

		if($this->knowledge->isEntryExpired($entry, null)) {
			return false;
		}

		if($this->injectOnlyAlways && !$this->entryAlwaysInject($entry)) {
			return false;
		}

		$status = (string)($entry['status'] ?? '');
		$allowed = $this->getInjectStatusesByType()[$memoryType] ?? [];

		if($allowed === []) {
			return $this->entryAlwaysInject($entry);
		}

		if(!in_array($status, $allowed, true)) {
			return false;
		}

		return true;
	}

	private function entryAlwaysInject(array $entry): bool {
		$meta = is_array($entry['meta_json'] ?? null) ? $entry['meta_json'] : [];

		if(array_key_exists('always_inject', $meta)) {
			return $this->toBool($meta['always_inject']);
		}

		if(array_key_exists('pinned', $meta)) {
			return $this->toBool($meta['pinned']);
		}

		return false;
	}

	private function getInjectStatusesByType(): array {
		return [
			'task' => ['open', 'in_progress', 'blocked'],
			'episodic' => ['open', 'in_progress', 'blocked', 'resolved', 'closed'],
			'semantic' => ['valid'],
			'procedural' => ['active'],
		];
	}

	// ----------------------------------------------------
	// Tool definitions
	// ----------------------------------------------------

	private function buildListTypesDefinition(): array {
		return [
			'type' => 'function',
			'label' => 'Knowledge Memory Types',
			'category' => 'memory',
			'tags' => ['knowledge', 'memory', 'types', 'metadata'],
			'priority' => 70,
			'function' => [
				'name' => 'knowledge_memory_list_types',
				'description' => 'List all available persistent knowledge memory types. Use this when you want to understand which knowledge categories exist before storing or searching memory.',
				'parameters' => [
					'type' => 'object',
					'properties' => new \stdClass()
				]
			]
		];
	}

	private function buildListStatusesDefinition(): array {
		return [
			'type' => 'function',
			'label' => 'Knowledge Memory Statuses',
			'category' => 'memory',
			'tags' => ['knowledge', 'memory', 'statuses', 'metadata'],
			'priority' => 70,
			'function' => [
				'name' => 'knowledge_memory_list_statuses',
				'description' => 'List allowed status values for one memory type or for all memory types. Use this before creating or updating memory if you need a valid status value.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'memory_type' => [
							'type' => 'string',
							'description' => 'Optional memory type. If omitted, returns statuses for all types.'
						]
					]
				]
			]
		];
	}

	private function buildGetEntryDefinition(): array {
		return [
			'type' => 'function',
			'label' => 'Get Knowledge Memory Entry',
			'category' => 'memory',
			'tags' => ['knowledge', 'memory', 'get', 'lookup'],
			'priority' => 65,
			'function' => [
				'name' => 'knowledge_memory_get_entry',
				'description' => 'Load one specific knowledge memory entry by ID. Use this when a previous search returned an entry ID and you want the full stored record.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'id' => [
							'type' => 'integer',
							'description' => 'Knowledge entry ID.'
						]
					],
					'required' => ['id']
				]
			]
		];
	}

	private function buildDeleteEntryDefinition(): array {
		return [
			'type' => 'function',
			'label' => 'Delete Knowledge Memory Entry',
			'category' => 'memory',
			'tags' => ['knowledge', 'memory', 'delete', 'cleanup'],
			'priority' => 60,
			'function' => [
				'name' => 'knowledge_memory_delete_entry',
				'description' => 'Soft-delete a knowledge memory entry by ID. Use this when stored knowledge is no longer wanted and should stop influencing future behavior.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'id' => [
							'type' => 'integer',
							'description' => 'Knowledge entry ID to delete.'
						]
					],
					'required' => ['id']
				]
			]
		];
	}

	private function buildGenericSearchDefinition(): array {
		return [
			'type' => 'function',
			'label' => 'Search Knowledge Memory',
			'category' => 'memory',
			'tags' => ['knowledge', 'memory', 'search', 'retrieval'],
			'priority' => 68,
			'function' => [
				'name' => 'knowledge_memory_search',
				'description' => 'Search persistent knowledge memory across all types or filter to one type. Use this when you are not yet sure whether the needed information is task, episodic, semantic, or procedural.',
				'parameters' => [
					'type' => 'object',
					'properties' => $this->buildSearchProperties(true)
				]
			]
		];
	}

	private function buildTypedSearchDefinition(string $name, string $label, string $description): array {
		return [
			'type' => 'function',
			'label' => $label,
			'category' => 'memory',
			'tags' => ['knowledge', 'memory', 'search'],
			'priority' => 72,
			'function' => [
				'name' => $name,
				'description' => $description,
				'parameters' => [
					'type' => 'object',
					'properties' => $this->buildSearchProperties(false)
				]
			]
		];
	}

	private function buildCreateDefinition(string $name, string $label, string $description): array {
		return [
			'type' => 'function',
			'label' => $label,
			'category' => 'memory',
			'tags' => ['knowledge', 'memory', 'create', 'store'],
			'priority' => 66,
			'function' => [
				'name' => $name,
				'description' => $description . ' Include a clear title and content. Use memory_key when this entry should later be upserted or merged as one stable canonical record.',
				'parameters' => [
					'type' => 'object',
					'properties' => $this->buildCreateProperties(),
					'required' => ['title', 'content']
				]
			]
		];
	}

	private function buildUpdateDefinition(string $name, string $label, string $description): array {
		return [
			'type' => 'function',
			'label' => $label,
			'category' => 'memory',
			'tags' => ['knowledge', 'memory', 'update', 'store'],
			'priority' => 66,
			'function' => [
				'name' => $name,
				'description' => $description . ' Use the entry ID from a previous search or lookup. Only pass the fields that should change.',
				'parameters' => [
					'type' => 'object',
					'properties' => $this->buildUpdateProperties(),
					'required' => ['id']
				]
			]
		];
	}

	private function buildUpsertDefinition(string $name, string $label, string $description): array {
		return [
			'type' => 'function',
			'label' => $label,
			'category' => 'memory',
			'tags' => ['knowledge', 'memory', 'upsert', 'store'],
			'priority' => 67,
			'function' => [
				'name' => $name,
				'description' => $description . ' It matches existing records primarily by memory_key when provided. If memory_key is missing, it falls back to title-based matching within the current identity context.',
				'parameters' => [
					'type' => 'object',
					'properties' => $this->buildCreateProperties(),
					'required' => ['title']
				]
			]
		];
	}

	private function buildProceduralApplicableDefinition(): array {
		return [
			'type' => 'function',
			'label' => 'Get Applicable Procedural Memory',
			'category' => 'memory',
			'tags' => ['knowledge', 'memory', 'procedural', 'workflow', 'applicable'],
			'priority' => 75,
			'function' => [
				'name' => 'procedural_memory_get_applicable',
				'description' => 'Find the most applicable active procedures, workflows, checklists, or SOPs for the current situation. Use this when you need actionable know-how or step-by-step guidance.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'query' => [
							'type' => 'string',
							'description' => 'Describe what should be done, fixed, diagnosed, or executed. This is used only as a soft ranking hint, not as a hard filter.'
						],
						'scope_ref' => [
							'type' => 'string',
							'description' => 'Optional scope reference to narrow results to a project, object, or case.'
						],
						'tags' => [
							'type' => 'array',
							'description' => 'Optional tags that should be matched.',
							'items' => [
								'type' => 'string'
							]
						],
						'entity_refs' => [
							'type' => 'array',
							'description' => 'Optional entity references that should be matched.',
							'items' => [
								'type' => 'string'
							]
						],
						'limit' => [
							'type' => 'integer',
							'description' => 'Maximum number of applicable procedures to return.'
						]
					],
					'required' => ['query']
				]
			]
		];
	}

	private function buildSearchProperties(bool $includeMemoryType): array {
		$properties = [
			'query' => [
				'type' => 'string',
				'description' => 'Optional natural-language hint for soft ranking. It never acts as a hard SQL text filter.'
			],
			'status' => [
				'type' => 'string',
				'description' => 'Optional status filter.'
			],
			'scope_ref' => [
				'type' => 'string',
				'description' => 'Optional scope reference such as a project ID, object key, or task ID.'
			],
			'tags' => [
				'type' => 'array',
				'description' => 'Optional tags that should be matched.',
				'items' => [
					'type' => 'string'
				]
			],
			'entity_refs' => [
				'type' => 'array',
				'description' => 'Optional entity references that should be matched.',
				'items' => [
					'type' => 'string'
				]
			],
			'not_expired' => [
				'type' => 'boolean',
				'description' => 'If true, only returns non-expired entries. Defaults to true.'
			],
			'limit' => [
				'type' => 'integer',
				'description' => 'Maximum number of entries to return.'
			]
		];

		if($includeMemoryType) {
			$properties = ['memory_type' => [
				'type' => 'string',
				'description' => 'Optional memory type filter: task, episodic, semantic, or procedural.'
			]] + $properties;
		}

		return $properties;
	}

	private function buildCreateProperties(): array {
		return [
			'title' => [
				'type' => 'string',
				'description' => 'Short human-readable title.'
			],
			'memory_key' => [
				'type' => 'string',
				'description' => 'Optional stable logical key for this memory entry. Use this when later upserts should target the same canonical record safely. For user preferences, use a stable key whenever possible.'
			],
			'content' => [
				'type' => 'string',
				'description' => 'Main stored content. Use clear natural language or structured text.'
			],
			'summary' => [
				'type' => 'string',
				'description' => 'Optional short summary for quick retrieval and prompt injection.'
			],
			'memory_subtype' => [
				'type' => 'string',
				'description' => 'Optional subtype such as case, incident, lesson_learned, fact, rule, workflow, checklist, skill, playbook, or user_preference.'
			],
			'status' => [
				'type' => 'string',
				'description' => 'Optional status. Use knowledge_memory_list_statuses if you need allowed values.'
			],
			'tags' => [
				'type' => 'array',
				'description' => 'Optional list of tags for retrieval.',
				'items' => [
					'type' => 'string'
				]
			],
			'entity_refs' => [
				'type' => 'array',
				'description' => 'Optional entity references such as user IDs, project keys, issue IDs, or customer identifiers.',
				'items' => [
					'type' => 'string'
				]
			],
			'scope' => [
				'type' => 'string',
				'description' => 'Optional write scope: user or session. If omitted, user is preferred and session is used as fallback.'
			],
			'scope_ref' => [
				'type' => 'string',
				'description' => 'Optional scope reference such as a project ID, task key, or case identifier.'
			],
			'source' => [
				'type' => 'string',
				'description' => 'Optional source label such as llm, manual, import, or system.'
			],
			'priority' => [
				'type' => 'integer',
				'description' => 'Optional priority. Higher values usually rank higher.'
			],
			'confidence' => [
				'type' => 'number',
				'description' => 'Optional confidence value between 0 and 1.'
			],
			'valid_from' => [
				'type' => 'string',
				'description' => 'Optional validity start as datetime string.'
			],
			'valid_to' => [
				'type' => 'string',
				'description' => 'Optional validity end as datetime string.'
			],
			'expires_at' => [
				'type' => 'string',
				'description' => 'Optional expiration datetime. Expired entries can be filtered out automatically.'
			],
			'always_inject' => [
				'type' => 'boolean',
				'description' => 'If true, the entry may be considered for automatic system prompt injection. Use this for persistent user preferences, answer style, and fixed behavioral rules.'
			],
			'inject_order' => [
				'type' => 'integer',
				'description' => 'Optional injection sort order. Lower numbers come first.'
			],
			'inject_group' => [
				'type' => 'string',
				'description' => 'Optional logical injection group.'
			],
			'is_locked' => [
				'type' => 'boolean',
				'description' => 'If true, the entry becomes locked against future changes.'
			],
			'is_mutable_by_llm' => [
				'type' => 'boolean',
				'description' => 'If false, later LLM-driven updates should be blocked.'
			],
			'is_deletable_by_llm' => [
				'type' => 'boolean',
				'description' => 'If false, later LLM-driven deletion should be blocked.'
			],
			'meta' => [
				'type' => 'object',
				'description' => 'Optional additional metadata object. Extra key-value pairs may be stored here.',
				'additionalProperties' => true
			]
		];
	}

	private function buildUpdateProperties(): array {
		return [
			'id' => [
				'type' => 'integer',
				'description' => 'Knowledge entry ID.'
			]
		] + $this->buildCreateProperties();
	}

	// ----------------------------------------------------
	// Entry mapping helpers
	// ----------------------------------------------------

	private function buildEntryDataFromArguments(array $arguments, bool $includeScope, ?array $existingEntry): array {
		$data = [];

		if(array_key_exists('title', $arguments)) {
			$data['title'] = trim((string)$arguments['title']);
		}
		if(array_key_exists('memory_key', $arguments)) {
			$memoryKey = $arguments['memory_key'];

			if($memoryKey === null) {
				$data['memory_key'] = null;
			}
			else {
				$memoryKey = trim((string)$memoryKey);
				$data['memory_key'] = ($memoryKey !== '') ? $memoryKey : null;
			}
		}
		if(array_key_exists('content', $arguments)) {
			$data['content'] = trim((string)$arguments['content']);
		}
		if(array_key_exists('summary', $arguments)) {
			$data['summary'] = trim((string)$arguments['summary']);
		}
		if(array_key_exists('memory_subtype', $arguments)) {
			$data['memory_subtype'] = trim((string)$arguments['memory_subtype']);
		}
		if(array_key_exists('status', $arguments)) {
			$data['status'] = trim((string)$arguments['status']);
		}
		if(array_key_exists('scope_ref', $arguments)) {
			$data['scope_ref'] = $arguments['scope_ref'] !== null ? trim((string)$arguments['scope_ref']) : null;
		}
		if($includeScope && array_key_exists('scope', $arguments)) {
			$data['scope'] = trim((string)$arguments['scope']);
		}
		if(array_key_exists('source', $arguments)) {
			$data['source'] = trim((string)$arguments['source']);
		}
		else if($existingEntry === null) {
			$data['source'] = 'llm';
		}
		if(array_key_exists('priority', $arguments)) {
			$data['priority'] = (int)$arguments['priority'];
		}
		if(array_key_exists('confidence', $arguments)) {
			$data['confidence'] = $arguments['confidence'] !== null ? (float)$arguments['confidence'] : null;
		}
		if(array_key_exists('valid_from', $arguments)) {
			$data['valid_from'] = $arguments['valid_from'] !== null ? trim((string)$arguments['valid_from']) : null;
		}
		if(array_key_exists('valid_to', $arguments)) {
			$data['valid_to'] = $arguments['valid_to'] !== null ? trim((string)$arguments['valid_to']) : null;
		}
		if(array_key_exists('expires_at', $arguments)) {
			$data['expires_at'] = $arguments['expires_at'] !== null ? trim((string)$arguments['expires_at']) : null;
		}
		if(array_key_exists('is_locked', $arguments)) {
			$data['is_locked'] = $this->toBool($arguments['is_locked']);
		}
		if(array_key_exists('is_mutable_by_llm', $arguments)) {
			$data['is_mutable_by_llm'] = $this->toBool($arguments['is_mutable_by_llm']);
		}
		if(array_key_exists('is_deletable_by_llm', $arguments)) {
			$data['is_deletable_by_llm'] = $this->toBool($arguments['is_deletable_by_llm']);
		}
		if(array_key_exists('tags', $arguments)) {
			$data['tags_json'] = $this->normalizeStringArray($arguments['tags']);
		}
		if(array_key_exists('entity_refs', $arguments)) {
			$data['entity_refs_json'] = $this->normalizeStringArray($arguments['entity_refs']);
		}

		$meta = [];
		$metaTouched = false;

		if($existingEntry !== null && is_array($existingEntry['meta_json'] ?? null)) {
			$meta = $existingEntry['meta_json'];
		}

		if(array_key_exists('meta', $arguments) && is_array($arguments['meta'])) {
			$meta = array_merge($meta, $arguments['meta']);
			$metaTouched = true;
		}

		if(array_key_exists('always_inject', $arguments)) {
			$meta['always_inject'] = $this->toBool($arguments['always_inject']);
			$metaTouched = true;
		}

		if(array_key_exists('inject_order', $arguments)) {
			$meta['inject_order'] = (int)$arguments['inject_order'];
			$metaTouched = true;
		}

		if(array_key_exists('inject_group', $arguments)) {
			$meta['inject_group'] = trim((string)$arguments['inject_group']);
			$metaTouched = true;
		}

		if($metaTouched) {
			$data['meta_json'] = $meta;
		}

		if($existingEntry === null) {
			$data['created_by'] = 'llm';
			$data['updated_by'] = 'llm';
		}
		else {
			$data['updated_by'] = 'llm';
		}

		return $data;
	}

	private function findExistingEntryForUpsert(string $memoryType, array $arguments): ?array {
		$memoryKey = trim((string)($arguments['memory_key'] ?? ''));

		if($memoryKey !== '') {
			$filters = [
				'memory_type' => $memoryType,
				'memory_key' => $memoryKey
			];

			if(array_key_exists('scope_ref', $arguments)) {
				$filters['scope_ref'] = $arguments['scope_ref'] !== null ? trim((string)$arguments['scope_ref']) : null;
			}

			$entries = $this->knowledge->findEntries($filters, 100, 0);

			if($entries !== []) {
				return $entries[0];
			}
		}

		$title = trim((string)($arguments['title'] ?? ''));

		if($title === '') {
			return null;
		}

		$filters = [
			'memory_type' => $memoryType
		];

		$memorySubtype = trim((string)($arguments['memory_subtype'] ?? ''));
		if($memorySubtype !== '') {
			$filters['memory_subtype'] = $memorySubtype;
		}

		if(array_key_exists('scope_ref', $arguments) && $arguments['scope_ref'] !== null && (string)$arguments['scope_ref'] !== '') {
			$filters['scope_ref'] = trim((string)$arguments['scope_ref']);
		}

		$entries = $this->knowledge->findEntries($filters, 100, 0);
		$needle = $this->normalizeTitle($title);

		foreach($entries as $entry) {
			$entryTitle = $this->normalizeTitle((string)($entry['title'] ?? ''));

			if($entryTitle === $needle) {
				return $entry;
			}
		}

		return null;
	}

	private function getDefaultStatusForType(string $memoryType): string {
		return match ($memoryType) {
			'task' => 'open',
			'episodic' => 'open',
			'semantic' => 'valid',
			'procedural' => 'active',
			default => ''
		};
	}

	// ----------------------------------------------------
	// Utilities
	// ----------------------------------------------------

	private function normalizeTitle(string $title): string {
		$title = trim($title);

		if($title === '') {
			return '';
		}

		if(function_exists('mb_strtolower')) {
			return mb_strtolower($title);
		}

		return strtolower($title);
	}

	private function normalizeStringArray(mixed $value): array {
		if($value === null) {
			return [];
		}

		if(is_string($value)) {
			$value = explode(',', $value);
		}

		if(!is_array($value)) {
			return [];
		}

		$out = [];

		foreach($value as $item) {
			$item = trim((string)$item);

			if($item === '') {
				continue;
			}

			$out[] = $item;
		}

		return array_values(array_unique($out));
	}

	private function toBool(mixed $value): bool {
		if(is_bool($value)) {
			return $value;
		}
		if(is_int($value)) {
			return $value !== 0;
		}

		$s = strtolower(trim((string)$value));
		return in_array($s, ['1', 'true', 'yes', 'on'], true);
	}

	private function truncate(string $text, int $maxLength): string {
		$text = trim($text);

		if($text === '') {
			return '';
		}

		if(function_exists('mb_strlen') && function_exists('mb_substr')) {
			if(mb_strlen($text) <= $maxLength) {
				return $text;
			}

			return rtrim(mb_substr($text, 0, $maxLength - 3)) . '...';
		}

		if(strlen($text) <= $maxLength) {
			return $text;
		}

		return rtrim(substr($text, 0, $maxLength - 3)) . '...';
	}

	private function log(string $msg): void {
		if($this->logger) {
			$this->logger->log(static::getName(), '[' . $this->id . '] ' . $msg);
		}
	}
}
