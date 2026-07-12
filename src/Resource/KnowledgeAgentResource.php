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
use AssistantFoundation\Api\IAgentContext;
use MissionBay\Api\IAgentKnowledgeService;
use MissionBay\Api\IAgentTool;

class KnowledgeAgentResource extends AbstractAgentResource implements IAgentTool, ISchemaProvider {

	private ?ILogger $logger = null;

	public function __construct(
		private readonly IAgentKnowledgeService $knowledge,
		?string $id = null
	) {
		parent::__construct($id);
	}

	public static function getName(): string {
		return 'knowledgeagentresource';
	}

	public function getDescription(): string {
		return 'Provides agent-owned knowledge and skills through explicit tool calls.';
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getSchema(): array {
		return [
			'$schema' => 'https://json-schema.org/draft-2020-12/schema',
			'type' => 'object',
			'properties' => [],
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
	}

	public function init(array $resources, IAgentContext $context): void {
		if(!empty($resources['logger'][0]) && $resources['logger'][0] instanceof ILogger) {
			$this->logger = $resources['logger'][0];
			$this->log('logger docked into KnowledgeAgentResource');
		}
	}

	// ----------------------------------------------------
	// IAgentTool
	// ----------------------------------------------------

	public function getToolDefinitions(): array {
		return [
			$this->buildSearchDefinition(),
			$this->buildGetEntryDefinition(),
			$this->buildUpsertDefinition(),
			$this->buildUpdateDefinition(),
			$this->buildDeleteEntryDefinition(),
			$this->buildProceduralApplicableDefinition()
		];
	}

	public function callTool(string $toolName, array $arguments, IAgentContext $context): mixed {
		return match ($toolName) {
			'knowledge_memory_search' => $this->toolSearchMemory(null, $arguments),
			'knowledge_memory_get_entry' => $this->toolGetEntry($arguments),
			'knowledge_memory_upsert' => $this->toolUpsertAnyMemory($arguments),
			'knowledge_memory_update' => $this->toolUpdateAnyMemory($arguments),
			'knowledge_memory_delete_entry' => $this->toolDeleteEntry($arguments),
			'procedural_memory_get_applicable' => $this->toolProceduralGetApplicable($arguments),

			// Compatibility aliases. They remain callable for saved flows but are no longer advertised.
			'knowledge_memory_list_types' => $this->toolListTypes(),
			'knowledge_memory_list_statuses' => $this->toolListStatuses($arguments),
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

	private function toolUpsertAnyMemory(array $arguments): array {
		$memoryType = strtolower(trim((string)($arguments['memory_type'] ?? '')));

		if (!in_array($memoryType, $this->knowledge->getMemoryTypes(), true)) {
			return ['error' => 'Missing or invalid parameter: memory_type'];
		}

		return $this->toolUpsertMemory($memoryType, $arguments);
	}

	private function toolUpdateAnyMemory(array $arguments): array {
		$id = (int)($arguments['id'] ?? 0);
		if ($id <= 0) {
			return ['error' => 'Missing or invalid parameter: id'];
		}

		$entry = $this->knowledge->getEntryById($id, false);
		if (!$entry) {
			return ['error' => 'Knowledge entry not found'];
		}

		$memoryType = (string)($entry['memory_type'] ?? '');
		if (!in_array($memoryType, $this->knowledge->getMemoryTypes(), true)) {
			return ['error' => 'Knowledge entry has an unsupported memory type'];
		}

		return $this->toolUpdateMemory($memoryType, $arguments);
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
	// Tool definitions
	// ----------------------------------------------------

	private function buildSearchDefinition(): array {
		return $this->readDefinition(
			'knowledge_memory_search',
			'Search Knowledge / Skills',
			'Search the agent knowledge and skills store. Do not use this tool to reconstruct the visible conversation; current chat history is already supplied to the model.',
			$this->buildSearchProperties(true),
			[]
		);
	}

	private function buildGetEntryDefinition(): array {
		return $this->readDefinition(
			'knowledge_memory_get_entry',
			'Get Knowledge Entry',
			'Load one knowledge or skill entry by ID.',
			['id' => ['type' => 'integer', 'description' => 'Knowledge entry ID.']],
			['id']
		);
	}

	private function buildUpsertDefinition(): array {
		return $this->writeDefinition(
			'knowledge_memory_upsert',
			'Save Knowledge / Skill',
			'Create or update one agent-owned task, episode, fact, rule, workflow, checklist, or skill. Use a stable memory_key when later calls should update the same record. This store is not the current conversation history.',
			$this->buildUpsertProperties(),
			['memory_type', 'title']
		);
	}

	private function buildUpdateDefinition(): array {
		return $this->writeDefinition(
			'knowledge_memory_update',
			'Update Knowledge Entry',
			'Update selected fields of an existing knowledge or skill entry. Use the ID returned by search or get.',
			$this->buildUpdateProperties(),
			['id']
		);
	}

	private function buildDeleteEntryDefinition(): array {
		$definition = $this->writeDefinition(
			'knowledge_memory_delete_entry',
			'Delete Knowledge Entry',
			'Soft-delete one knowledge or skill entry by ID.',
			['id' => ['type' => 'integer', 'description' => 'Knowledge entry ID.']],
			['id']
		);
		return $definition;
	}

	private function buildProceduralApplicableDefinition(): array {
		return $this->readDefinition(
			'procedural_memory_get_applicable',
			'Find Applicable Skills',
			'Find active procedures, workflows, checklists, SOPs, or skills that apply to the current task.',
			[
				'query' => ['type' => 'string', 'description' => 'What should be done or solved.'],
				'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
				'scope_ref' => ['type' => 'string'],
				'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20]
			],
			['query']
		);
	}

	private function readDefinition(string $name, string $label, string $description, array $properties, array $required): array {
		return [
			'type' => 'function',
			'label' => $label,
			'category' => 'memory',
			'tags' => ['knowledge', 'memory', 'skills', 'readonly'],
			'priority' => 72,
			'readOnlyHint' => true,
			'function' => [
				'name' => $name,
				'description' => $description,
				'parameters' => [
					'type' => 'object',
					'properties' => $properties,
					'required' => $required
				]
			]
		];
	}

	private function writeDefinition(string $name, string $label, string $description, array $properties, array $required): array {
		$definition = $this->readDefinition($name, $label, $description, $properties, $required);
		$definition['tags'] = ['knowledge', 'memory', 'skills', 'internal-write'];
		$definition['readOnlyHint'] = false;
		$definition['mutation'] = true;
		$definition['requiresApproval'] = false;
		$definition['commitGuardRequired'] = false;
		$definition['internalStateWrite'] = true;
		$definition['cacheable'] = false;

		return $definition;
	}

	private function buildSearchProperties(bool $includeMemoryType): array {
		$properties = [
			'query' => ['type' => 'string', 'description' => 'Natural-language search hint.'],
			'status' => ['type' => 'string'],
			'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
			'scope_ref' => ['type' => 'string'],
			'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50]
		];

		if ($includeMemoryType) {
			$properties = ['memory_type' => [
				'type' => 'string',
				'enum' => ['task', 'episodic', 'semantic', 'procedural']
			]] + $properties;
		}

		return $properties;
	}

	private function buildUpsertProperties(): array {
		return [
			'memory_type' => ['type' => 'string', 'enum' => ['task', 'episodic', 'semantic', 'procedural']],
			'title' => ['type' => 'string'],
			'memory_key' => ['type' => 'string', 'description' => 'Stable key for canonical upserts.'],
			'content' => ['type' => 'string'],
			'summary' => ['type' => 'string'],
			'memory_subtype' => ['type' => 'string', 'description' => 'For example skill, workflow, checklist, fact, rule, or user_preference.'],
			'status' => ['type' => 'string'],
			'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
			'scope_ref' => ['type' => 'string'],
		];
	}

	private function buildUpdateProperties(): array {
		$properties = $this->buildUpsertProperties();
		unset($properties['memory_type'], $properties['memory_key']);

		return ['id' => ['type' => 'integer']] + $properties;
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
