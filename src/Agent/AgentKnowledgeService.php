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

namespace MissionBay\Agent;

use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Database\Api\IDatabase;
use Base3\Session\Api\ISession;
use MissionBay\Api\IAgentKnowledgeService;

/**
 * Default persistent knowledge store for agent-related long-term knowledge.
 *
 * This service manages four persistent knowledge types:
 * - task
 * - episodic
 * - semantic
 * - procedural
 *
 * Entries are stored per current identity:
 * - preferred scope: user
 * - fallback scope: session if no authenticated user exists
 *
 * Read operations are identity-aware:
 * - session entries are loaded for the current session
 * - user entries are loaded for the current user
 * - if both exist, user entries override session entries for the same logical slot
 */
class AgentKnowledgeService implements IAgentKnowledgeService {

	protected IDatabase $db;

	protected IAccesscontrol $accesscontrol;

	protected ISession $session;

	protected bool $ensured = false;

	protected string $table = 'base3_agent_knowledge';

	/**
	 * Supported memory types.
	 *
	 * @var array<int,string>
	 */
	protected array $memoryTypes = [
		'task',
		'episodic',
		'semantic',
		'procedural',
	];

	/**
	 * Allowed status values per memory type.
	 *
	 * @var array<string,array<int,string>>
	 */
	protected array $allowedStatuses = [
		'task' => [
			'open',
			'in_progress',
			'blocked',
			'resolved',
			'closed',
			'cancelled',
		],
		'episodic' => [
			'open',
			'in_progress',
			'blocked',
			'resolved',
			'closed',
			'cancelled',
		],
		'semantic' => [
			'draft',
			'valid',
			'deprecated',
			'superseded',
			'invalid',
		],
		'procedural' => [
			'draft',
			'active',
			'paused',
			'retired',
			'archived',
		],
	];

	public function __construct(IDatabase $db, IAccesscontrol $accesscontrol, ISession $session) {
		$this->db = $db;
		$this->accesscontrol = $accesscontrol;
		$this->session = $session;
	}

	public function getMemoryTypes(): array {
		return $this->memoryTypes;
	}

	public function getAllowedStatuses(string $memoryType): array {
		return $this->allowedStatuses[$memoryType] ?? [];
	}

	public function createEntry(array $data): int {
		$this->ensure();

		$memoryType = (string)($data['memory_type'] ?? '');
		$memoryKey = trim((string)($data['memory_key'] ?? ''));
		$title = trim((string)($data['title'] ?? ''));
		$content = trim((string)($data['content'] ?? ''));
		$status = $data['status'] ?? null;

		$this->assertValidMemoryType($memoryType);

		if($title === '') {
			throw new \InvalidArgumentException('Missing required field: title');
		}

		if($content === '') {
			throw new \InvalidArgumentException('Missing required field: content');
		}

		if(!$this->isValidStatusForType($memoryType, is_string($status) ? $status : null)) {
			throw new \InvalidArgumentException('Invalid status for memory type: ' . $memoryType);
		}

		$scope = $this->resolveFinalScope((string)($data['scope'] ?? ''));
		$ids = $this->resolveScopeIds($scope);

		$query = 'INSERT INTO `' . $this->table . '` SET '
			. '`memory_type` = ' . $this->quote($memoryType) . ', '
			. '`memory_key` = ' . $this->nullableString($memoryKey !== '' ? $memoryKey : null) . ', '
			. '`memory_subtype` = ' . $this->nullableString($data['memory_subtype'] ?? null) . ', '
			. '`status` = ' . $this->nullableString($status) . ', '
			. '`title` = ' . $this->quote($title) . ', '
			. '`content` = ' . $this->quote($content) . ', '
			. '`summary` = ' . $this->nullableString($data['summary'] ?? null) . ', '
			. '`tags_json` = ' . $this->nullableJson($data['tags_json'] ?? null) . ', '
			. '`entity_refs_json` = ' . $this->nullableJson($data['entity_refs_json'] ?? null) . ', '
			. '`meta_json` = ' . $this->nullableJson($data['meta_json'] ?? null) . ', '
			. '`source` = ' . $this->quote((string)($data['source'] ?? 'manual')) . ', '
			. '`scope` = ' . $this->quote($scope) . ', '
			. '`scope_ref` = ' . $this->nullableString($data['scope_ref'] ?? null) . ', '
			. '`ident` = ' . $this->quote($ids['ident']) . ', '
			. '`userid` = ' . $this->nullableString($ids['userid']) . ', '
			. '`session` = ' . $this->nullableString($ids['session']) . ', '
			. '`is_locked` = ' . $this->boolInt($data['is_locked'] ?? false) . ', '
			. '`is_mutable_by_llm` = ' . $this->boolInt($data['is_mutable_by_llm'] ?? true) . ', '
			. '`is_deletable_by_llm` = ' . $this->boolInt($data['is_deletable_by_llm'] ?? true) . ', '
			. '`is_deleted` = 0, '
			. '`priority` = ' . (int)($data['priority'] ?? 0) . ', '
			. '`confidence` = ' . $this->nullableDecimal($data['confidence'] ?? null) . ', '
			. '`valid_from` = ' . $this->nullableDateTime($data['valid_from'] ?? null) . ', '
			. '`valid_to` = ' . $this->nullableDateTime($data['valid_to'] ?? null) . ', '
			. '`expires_at` = ' . $this->nullableDateTime($data['expires_at'] ?? null) . ', '
			. '`last_accessed_at` = NULL, '
			. '`created_by` = ' . $this->nullableString($data['created_by'] ?? null) . ', '
			. '`updated_by` = ' . $this->nullableString($data['updated_by'] ?? null);

		$this->db->connect();
		$this->db->nonQuery($query);

		if($this->db->isError()) {
			throw new \RuntimeException('Failed to create knowledge entry: ' . $this->db->errorMessage());
		}

		return (int)$this->db->insertId();
	}

	public function updateEntry(int $id, array $data): bool {
		$this->ensure();

		$entry = $this->getEntryById($id, true);

		if($entry === null) {
			return false;
		}

		if(!$this->canAccessEntry($entry)) {
			return false;
		}

		if((int)($entry['is_locked'] ?? 0) === 1) {
			return false;
		}

		$updatedBy = array_key_exists('updated_by', $data) ? (string)$data['updated_by'] : null;

		if($updatedBy === 'llm' && !$this->isMutableByLlm($entry)) {
			return false;
		}

		$memoryType = (string)($data['memory_type'] ?? $entry['memory_type'] ?? '');

		$this->assertValidMemoryType($memoryType);

		if(array_key_exists('status', $data)) {
			$status = $data['status'];

			if(!$this->isValidStatusForType($memoryType, is_string($status) ? $status : null)) {
				throw new \InvalidArgumentException('Invalid status for memory type: ' . $memoryType);
			}
		}

		if(array_key_exists('title', $data)) {
			$title = trim((string)$data['title']);

			if($title === '') {
				throw new \InvalidArgumentException('Field title must not be empty');
			}
		}

		if(array_key_exists('content', $data)) {
			$content = trim((string)$data['content']);

			if($content === '') {
				throw new \InvalidArgumentException('Field content must not be empty');
			}
		}

		$set = [];

		if(array_key_exists('memory_type', $data)) {
			$set[] = '`memory_type` = ' . $this->quote($memoryType);
		}
		if(array_key_exists('memory_key', $data)) {
			$memoryKey = $data['memory_key'];

			if($memoryKey !== null) {
				$memoryKey = trim((string)$memoryKey);
				$memoryKey = ($memoryKey !== '') ? $memoryKey : null;
			}

			$set[] = '`memory_key` = ' . $this->nullableString($memoryKey);
		}
		if(array_key_exists('memory_subtype', $data)) {
			$set[] = '`memory_subtype` = ' . $this->nullableString($data['memory_subtype']);
		}
		if(array_key_exists('status', $data)) {
			$set[] = '`status` = ' . $this->nullableString($data['status']);
		}
		if(array_key_exists('title', $data)) {
			$set[] = '`title` = ' . $this->quote(trim((string)$data['title']));
		}
		if(array_key_exists('content', $data)) {
			$set[] = '`content` = ' . $this->quote(trim((string)$data['content']));
		}
		if(array_key_exists('summary', $data)) {
			$set[] = '`summary` = ' . $this->nullableString($data['summary']);
		}
		if(array_key_exists('tags_json', $data)) {
			$set[] = '`tags_json` = ' . $this->nullableJson($data['tags_json']);
		}
		if(array_key_exists('entity_refs_json', $data)) {
			$set[] = '`entity_refs_json` = ' . $this->nullableJson($data['entity_refs_json']);
		}
		if(array_key_exists('meta_json', $data)) {
			$set[] = '`meta_json` = ' . $this->nullableJson($data['meta_json']);
		}
		if(array_key_exists('source', $data)) {
			$set[] = '`source` = ' . $this->quote((string)$data['source']);
		}
		if(array_key_exists('scope_ref', $data)) {
			$set[] = '`scope_ref` = ' . $this->nullableString($data['scope_ref']);
		}
		if(array_key_exists('is_locked', $data)) {
			$set[] = '`is_locked` = ' . $this->boolInt($data['is_locked']);
		}
		if(array_key_exists('is_mutable_by_llm', $data)) {
			$set[] = '`is_mutable_by_llm` = ' . $this->boolInt($data['is_mutable_by_llm']);
		}
		if(array_key_exists('is_deletable_by_llm', $data)) {
			$set[] = '`is_deletable_by_llm` = ' . $this->boolInt($data['is_deletable_by_llm']);
		}
		if(array_key_exists('is_deleted', $data)) {
			$set[] = '`is_deleted` = ' . $this->boolInt($data['is_deleted']);
		}
		if(array_key_exists('priority', $data)) {
			$set[] = '`priority` = ' . (int)$data['priority'];
		}
		if(array_key_exists('confidence', $data)) {
			$set[] = '`confidence` = ' . $this->nullableDecimal($data['confidence']);
		}
		if(array_key_exists('valid_from', $data)) {
			$set[] = '`valid_from` = ' . $this->nullableDateTime($data['valid_from']);
		}
		if(array_key_exists('valid_to', $data)) {
			$set[] = '`valid_to` = ' . $this->nullableDateTime($data['valid_to']);
		}
		if(array_key_exists('expires_at', $data)) {
			$set[] = '`expires_at` = ' . $this->nullableDateTime($data['expires_at']);
		}
		if(array_key_exists('last_accessed_at', $data)) {
			$set[] = '`last_accessed_at` = ' . $this->nullableDateTime($data['last_accessed_at']);
		}
		if(array_key_exists('updated_by', $data)) {
			$set[] = '`updated_by` = ' . $this->nullableString($data['updated_by']);
		}

		if($set === []) {
			return true;
		}

		$query = 'UPDATE `' . $this->table . '` SET '
			. implode(', ', $set)
			. ' WHERE `id` = ' . (int)$id;

		$this->db->connect();
		$this->db->nonQuery($query);

		if($this->db->isError()) {
			throw new \RuntimeException('Failed to update knowledge entry: ' . $this->db->errorMessage());
		}

		return true;
	}

	public function deleteEntry(int $id, ?string $deletedBy = null): bool {
		$this->ensure();

		$entry = $this->getEntryById($id, true);

		if($entry === null) {
			return false;
		}

		if(!$this->canAccessEntry($entry)) {
			return false;
		}

		if((int)($entry['is_locked'] ?? 0) === 1) {
			return false;
		}

		if((int)($entry['is_deletable_by_llm'] ?? 1) !== 1 && $deletedBy === 'llm') {
			return false;
		}

		$query = 'UPDATE `' . $this->table . '` SET '
			. '`is_deleted` = 1, '
			. '`updated_by` = ' . $this->nullableString($deletedBy)
			. ' WHERE `id` = ' . (int)$id;

		$this->db->connect();
		$this->db->nonQuery($query);

		if($this->db->isError()) {
			throw new \RuntimeException('Failed to delete knowledge entry: ' . $this->db->errorMessage());
		}

		return true;
	}

	public function getEntryById(int $id, bool $includeDeleted = false): ?array {
		$this->ensure();

		$where = [
			'`id` = ' . (int)$id,
		];

		if(!$includeDeleted) {
			$where[] = '`is_deleted` = 0';
		}

		$query = 'SELECT * FROM `' . $this->table . '` WHERE ' . implode(' AND ', $where) . ' LIMIT 1';

		$this->db->connect();
		$row = $this->db->singleQuery($query);

		if($row === null) {
			return null;
		}

		$entry = $this->hydrateRow($row);

		if(!$this->canAccessEntry($entry)) {
			return null;
		}

		return $entry;
	}

	public function findEntries(array $filters = [], int $limit = 50, int $offset = 0): array {
		$this->ensure();

		$rows = $this->loadEntriesRaw($filters, $limit * 3, $offset);
		$rows = $this->mergeIdentityRows($rows);

		if($limit > 0) {
			$rows = array_slice($rows, 0, $limit);
		}

		return $rows;
	}

	public function loadCuratedEntries(array $options = [], int $limit = 20, int $offset = 0): array {
		$this->ensure();

		$where = [
			'`is_deleted` = 0',
		];

		$where = array_merge($where, $this->buildIdentityConditions(true));
		$where = array_merge($where, $this->buildFilterConditions($options));

		if(array_key_exists('always_inject', $options)) {
			$where[] = $this->buildInjectFlagSqlCondition($this->toSqlBool($options['always_inject']));
		}

		$sql = 'SELECT * FROM `' . $this->table . '`';

		if($where !== []) {
			$sql .= ' WHERE ' . implode(' AND ', $where);
		}

		$sql .= ' ORDER BY ' . $this->buildCuratedOrderSql();
		$sql .= ' LIMIT ' . max(0, $offset) . ', ' . max(1, $limit * 3);

		$this->db->connect();
		$rows = $this->db->multiQuery($sql);

		$entries = $this->hydrateRows($rows);
		$entries = $this->mergeIdentityRows($entries);
		$entries = $this->sortCuratedEntries($entries);

		return array_slice($entries, 0, $limit);
	}

	public function searchEntries(string $query, array $options = [], int $limit = 20, int $offset = 0): array {
		$this->ensure();

		$limit = max(1, $limit);
		$offset = max(0, $offset);
		$includeDeleted = (bool)($options['include_deleted'] ?? false);

		$filters = $this->buildSearchCandidateFilters($options);
		$candidateLimit = $this->resolveSearchCandidateLimit($limit, $offset);
		$candidates = [];

		foreach($this->buildSearchFallbackProfiles($filters) as $profile) {
			$candidates = $this->loadSearchCandidates($profile, $candidateLimit, 0, $includeDeleted);

			if($candidates !== []) {
				break;
			}
		}

		if($candidates === []) {
			return [];
		}

		$ranked = $this->rankSearchEntries($candidates, $query);

		return array_slice($ranked, $offset, $limit);
	}

	public function buildPromptExtract(string $query, array $options = [], int $limit = 10): string {
		$this->ensure();

		$entries = $this->searchEntries($query, $options, $limit, 0);

		if($entries === []) {
			return '';
		}

		$lines = [];

		foreach($entries as $entry) {
			$label = strtoupper((string)$entry['memory_type']);
			$title = trim((string)($entry['title'] ?? ''));
			$summary = trim((string)($entry['summary'] ?? ''));

			if($summary === '') {
				$summary = $this->truncate((string)($entry['content'] ?? ''), 400);
			}

			$line = '[' . $label . ']';

			if($title !== '') {
				$line .= ' ' . $title . ':';
			}

			$line .= ' ' . $summary;

			$lines[] = trim($line);
		}

		return implode("\n", $lines);
	}

	public function touchEntry(int $id): bool {
		$this->ensure();

		$entry = $this->getEntryById($id, true);

		if($entry === null) {
			return false;
		}

		if(!$this->canAccessEntry($entry)) {
			return false;
		}

		$query = 'UPDATE `' . $this->table . '` SET '
			. '`last_accessed_at` = NOW() '
			. 'WHERE `id` = ' . (int)$id;

		$this->db->connect();
		$this->db->nonQuery($query);

		if($this->db->isError()) {
			throw new \RuntimeException('Failed to touch knowledge entry: ' . $this->db->errorMessage());
		}

		return $this->db->affectedRows() > 0;
	}

	public function isValidStatusForType(string $memoryType, ?string $status): bool {
		$this->assertValidMemoryType($memoryType);

		if($status === null || $status === '') {
			return true;
		}

		return in_array($status, $this->getAllowedStatuses($memoryType), true);
	}

	public function isMutableByLlm(array $entry): bool {
		if((int)($entry['is_locked'] ?? 0) === 1) {
			return false;
		}

		return (int)($entry['is_mutable_by_llm'] ?? 1) === 1;
	}

	public function isDeletableByLlm(array $entry): bool {
		if((int)($entry['is_locked'] ?? 0) === 1) {
			return false;
		}

		return (int)($entry['is_deletable_by_llm'] ?? 1) === 1;
	}

	public function isEntryValidAt(array $entry, ?string $at = null): bool {
		$atTs = $this->toTimestamp($at);

		if($atTs === null) {
			$atTs = time();
		}

		$validFrom = $this->toTimestamp($entry['valid_from'] ?? null);
		$validTo = $this->toTimestamp($entry['valid_to'] ?? null);

		if($validFrom !== null && $atTs < $validFrom) {
			return false;
		}

		if($validTo !== null && $atTs > $validTo) {
			return false;
		}

		return true;
	}

	public function isEntryExpired(array $entry, ?string $at = null): bool {
		$atTs = $this->toTimestamp($at);

		if($atTs === null) {
			$atTs = time();
		}

		$expiresAt = $this->toTimestamp($entry['expires_at'] ?? null);

		if($expiresAt === null) {
			return false;
		}

		return $atTs >= $expiresAt;
	}

	/**
	 * Ensures that the database table exists.
	 */
	protected function ensure(): void {
		if($this->ensured) {
			return;
		}

		$this->db->connect();

		$query = 'CREATE TABLE IF NOT EXISTS `' . $this->table . '` ('
			. '`id` bigint unsigned NOT NULL AUTO_INCREMENT,'
			. '`memory_type` enum(\'task\',\'episodic\',\'semantic\',\'procedural\') NOT NULL,'
			. '`memory_key` varchar(191) DEFAULT NULL,'
			. '`memory_subtype` varchar(64) DEFAULT NULL,'
			. '`status` varchar(32) DEFAULT NULL,'
			. '`title` varchar(255) NOT NULL,'
			. '`content` mediumtext NOT NULL,'
			. '`summary` text DEFAULT NULL,'
			. '`tags_json` json DEFAULT NULL,'
			. '`entity_refs_json` json DEFAULT NULL,'
			. '`meta_json` json DEFAULT NULL,'
			. '`source` varchar(32) NOT NULL DEFAULT \'manual\','
			. '`scope` varchar(32) NOT NULL DEFAULT \'user\','
			. '`scope_ref` varchar(191) DEFAULT NULL,'
			. '`ident` varchar(128) NOT NULL,'
			. '`userid` varchar(64) DEFAULT NULL,'
			. '`session` varchar(128) DEFAULT NULL,'
			. '`is_locked` tinyint(1) NOT NULL DEFAULT 0,'
			. '`is_mutable_by_llm` tinyint(1) NOT NULL DEFAULT 1,'
			. '`is_deletable_by_llm` tinyint(1) NOT NULL DEFAULT 1,'
			. '`is_deleted` tinyint(1) NOT NULL DEFAULT 0,'
			. '`priority` int NOT NULL DEFAULT 0,'
			. '`confidence` decimal(5,4) DEFAULT NULL,'
			. '`valid_from` datetime DEFAULT NULL,'
			. '`valid_to` datetime DEFAULT NULL,'
			. '`expires_at` datetime DEFAULT NULL,'
			. '`last_accessed_at` datetime DEFAULT NULL,'
			. '`created_by` varchar(64) DEFAULT NULL,'
			. '`updated_by` varchar(64) DEFAULT NULL,'
			. '`created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,'
			. '`updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,'
			. 'PRIMARY KEY (`id`),'
			. 'KEY `idx_memory_type` (`memory_type`),'
			. 'KEY `idx_memory_key` (`memory_key`),'
			. 'KEY `idx_status` (`status`),'
			. 'KEY `idx_scope` (`scope`,`scope_ref`),'
			. 'KEY `idx_ident` (`ident`),'
			. 'KEY `idx_userid` (`userid`),'
			. 'KEY `idx_session` (`session`),'
			. 'KEY `idx_is_deleted` (`is_deleted`),'
			. 'KEY `idx_locked` (`is_locked`),'
			. 'KEY `idx_expires_at` (`expires_at`),'
			. 'KEY `idx_valid_to` (`valid_to`),'
			. 'KEY `idx_priority` (`priority`),'
			. 'KEY `idx_type_status_scope` (`memory_type`,`status`,`scope`,`scope_ref`),'
			. 'KEY `idx_identity_lookup` (`ident`,`memory_type`,`status`,`is_deleted`),'
			. 'KEY `idx_type_key_scope_ident` (`memory_type`,`memory_key`,`scope_ref`,`ident`)'
			. ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		$this->db->nonQuery($query);

		if($this->db->isError()) {
			throw new \RuntimeException('Failed to ensure knowledge table: ' . $this->db->errorMessage());
		}

		$this->ensured = true;
	}

	/**
	 * Loads raw entries before identity merge.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function loadEntriesRaw(array $filters, int $limit, int $offset): array {
		$query = 'SELECT * FROM `' . $this->table . '`'
			. $this->buildWhereClause($filters, true)
			. ' ORDER BY `priority` DESC, `updated_at` DESC, `id` DESC'
			. ' LIMIT ' . max(0, $offset) . ', ' . max(1, $limit);

		$this->db->connect();
		$rows = $this->db->multiQuery($query);

		return $this->hydrateRows($rows);
	}

	/**
	 * Loads broad search candidates before any PHP-side ranking reduction happens.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function loadSearchCandidates(array $filters, int $limit, int $offset = 0, bool $includeDeleted = false): array {
		$queryFilters = $filters;

		if($includeDeleted) {
			$queryFilters['include_deleted'] = true;
		}

		$query = 'SELECT * FROM `' . $this->table . '`'
			. $this->buildWhereClause($queryFilters, true)
			. ' ORDER BY `priority` DESC, `updated_at` DESC, `id` DESC'
			. ' LIMIT ' . max(0, $offset) . ', ' . max(1, $limit * 3);

		$this->db->connect();
		$rows = $this->db->multiQuery($query);

		$entries = $this->hydrateRows($rows);
		$entries = $this->mergeIdentityRows($entries);
		$entries = $this->sortBaseSearchEntries($entries);

		return array_slice($entries, 0, $limit);
	}

	/**
	 * Builds the broad candidate filter set used by searchEntries().
	 *
	 * The natural-language query is intentionally not part of the SQL WHERE clause.
	 */
	protected function buildSearchCandidateFilters(array $options): array {
		$filters = [];

		if(array_key_exists('memory_type', $options) && $options['memory_type'] !== null && $options['memory_type'] !== '') {
			$filters['memory_type'] = $options['memory_type'];
		}

		if(array_key_exists('status', $options) && $options['status'] !== null && $options['status'] !== '') {
			$filters['status'] = $options['status'];
		}

		if(array_key_exists('scope_ref', $options)) {
			$filters['scope_ref'] = $options['scope_ref'];
		}

		if(!empty($options['tags']) && is_array($options['tags'])) {
			$tags = [];

			foreach($options['tags'] as $tag) {
				$tag = trim((string)$tag);

				if($tag === '') {
					continue;
				}

				$tags[] = $tag;
			}

			if($tags !== []) {
				$filters['tags'] = array_values(array_unique($tags));
			}
		}

		if(!empty($options['entity_refs']) && is_array($options['entity_refs'])) {
			$entityRefs = [];

			foreach($options['entity_refs'] as $entityRef) {
				$entityRef = trim((string)$entityRef);

				if($entityRef === '') {
					continue;
				}

				$entityRefs[] = $entityRef;
			}

			if($entityRefs !== []) {
				$filters['entity_refs'] = array_values(array_unique($entityRefs));
			}
		}

		if((bool)($options['not_expired'] ?? false)) {
			$filters['not_expired'] = true;
		}

		return $filters;
	}

	/**
	 * Builds fallback profiles that progressively relax non-identity filters.
	 *
	 * The goal is to prefer broad delivery over empty results.
	 *
	 * @param array<string,mixed> $filters
	 * @return array<int,array<string,mixed>>
	 */
	protected function buildSearchFallbackProfiles(array $filters): array {
		$profiles = [];

		$this->addSearchFallbackProfile($profiles, $filters);

		$withoutSoftRefs = $filters;
		unset($withoutSoftRefs['tags'], $withoutSoftRefs['entity_refs']);
		$this->addSearchFallbackProfile($profiles, $withoutSoftRefs);

		$withoutScope = $withoutSoftRefs;
		unset($withoutScope['scope_ref']);
		$this->addSearchFallbackProfile($profiles, $withoutScope);

		$withoutStatus = $withoutScope;
		unset($withoutStatus['status']);
		$this->addSearchFallbackProfile($profiles, $withoutStatus);

		if(array_key_exists('memory_type', $filters)) {
			$typeOnly = [
				'memory_type' => $filters['memory_type'],
			];

			if(array_key_exists('not_expired', $filters)) {
				$typeOnly['not_expired'] = $filters['not_expired'];
			}

			$this->addSearchFallbackProfile($profiles, $typeOnly);
		}

		$identityOnly = [];

		if(array_key_exists('not_expired', $filters)) {
			$identityOnly['not_expired'] = $filters['not_expired'];
		}

		$this->addSearchFallbackProfile($profiles, $identityOnly);

		return $profiles;
	}

	/**
	 * Adds one fallback profile if it is not already present.
	 *
	 * @param array<int,array<string,mixed>> $profiles
	 * @param array<string,mixed> $candidate
	 */
	protected function addSearchFallbackProfile(array &$profiles, array $candidate): void {
		foreach($profiles as $existing) {
			if($existing == $candidate) {
				return;
			}
		}

		$profiles[] = $candidate;
	}

	/**
	 * Returns the number of broad candidates loaded before PHP ranking.
	 */
	protected function resolveSearchCandidateLimit(int $limit, int $offset): int {
		$requested = max(1, ($limit + $offset) * 5);

		return max(50, min(100, $requested));
	}

	/**
	 * Ranks search entries in PHP and uses the query only as a weak signal.
	 *
	 * If the query does not yield any soft matches, the broad top candidates stay intact.
	 *
	 * @param array<int,array<string,mixed>> $entries
	 * @return array<int,array<string,mixed>>
	 */
	protected function rankSearchEntries(array $entries, string $query): array {
		if($entries === []) {
			return [];
		}

		$entries = $this->sortBaseSearchEntries($entries);
		$tokens = $this->tokenizeSearchQuery($query);

		if($tokens === []) {
			return $entries;
		}

		$preferred = [];
		$fallback = [];

		foreach($entries as $entry) {
			$entry['_search_score'] = $this->getSearchQueryScore($entry, $tokens);

			if((int)$entry['_search_score'] > 0) {
				$preferred[] = $entry;
				continue;
			}

			$fallback[] = $entry;
		}

		if($preferred !== []) {
			usort($preferred, function(array $a, array $b): int {
				return $this->compareMatchedSearchEntries($a, $b);
			});

			$entries = array_merge($preferred, $fallback);
		}

		foreach($entries as &$entry) {
			unset($entry['_search_score']);
		}
		unset($entry);

		return $entries;
	}

	/**
	 * Sorts entries by the default broad search order.
	 *
	 * @param array<int,array<string,mixed>> $entries
	 * @return array<int,array<string,mixed>>
	 */
	protected function sortBaseSearchEntries(array $entries): array {
		usort($entries, function(array $a, array $b): int {
			return $this->compareBaseSearchEntries($a, $b);
		});

		return $entries;
	}

	/**
	 * Comparator for broad candidate ordering.
	 */
	protected function compareBaseSearchEntries(array $a, array $b): int {
		$aPriority = (int)($a['priority'] ?? 0);
		$bPriority = (int)($b['priority'] ?? 0);

		if($aPriority !== $bPriority) {
			return $bPriority <=> $aPriority;
		}

		$aUpdated = strtotime((string)($a['updated_at'] ?? '')) ?: 0;
		$bUpdated = strtotime((string)($b['updated_at'] ?? '')) ?: 0;

		if($aUpdated !== $bUpdated) {
			return $bUpdated <=> $aUpdated;
		}

		$aOrder = $this->getInjectOrder($a);
		$bOrder = $this->getInjectOrder($b);

		if($aOrder !== $bOrder) {
			return $aOrder <=> $bOrder;
		}

		return (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0);
	}

	/**
	 * Comparator for query-matched candidates.
	 */
	protected function compareMatchedSearchEntries(array $a, array $b): int {
		$aPriority = (int)($a['priority'] ?? 0);
		$bPriority = (int)($b['priority'] ?? 0);

		if($aPriority !== $bPriority) {
			return $bPriority <=> $aPriority;
		}

		$aScore = (int)($a['_search_score'] ?? 0);
		$bScore = (int)($b['_search_score'] ?? 0);

		if($aScore !== $bScore) {
			return $bScore <=> $aScore;
		}

		$aUpdated = strtotime((string)($a['updated_at'] ?? '')) ?: 0;
		$bUpdated = strtotime((string)($b['updated_at'] ?? '')) ?: 0;

		if($aUpdated !== $bUpdated) {
			return $bUpdated <=> $aUpdated;
		}

		$aOrder = $this->getInjectOrder($a);
		$bOrder = $this->getInjectOrder($b);

		if($aOrder !== $bOrder) {
			return $aOrder <=> $bOrder;
		}

		return (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0);
	}

	/**
	 * Returns a weak text score for one entry.
	 *
	 * The score is only used for soft ranking and never for hard filtering.
	 *
	 * @param array<int,string> $tokens
	 */
	protected function getSearchQueryScore(array $entry, array $tokens): int {
		$fieldTexts = [
			'memory_key' => $this->stringifySearchValue($entry['memory_key'] ?? null),
			'title' => $this->stringifySearchValue($entry['title'] ?? null),
			'summary' => $this->stringifySearchValue($entry['summary'] ?? null),
			'tags' => $this->stringifySearchValue($entry['tags_json'] ?? null),
			'entity_refs' => $this->stringifySearchValue($entry['entity_refs_json'] ?? null),
			'memory_subtype' => $this->stringifySearchValue($entry['memory_subtype'] ?? null),
			'scope_ref' => $this->stringifySearchValue($entry['scope_ref'] ?? null),
			'content' => $this->stringifySearchValue($entry['content'] ?? null),
		];

		$weights = [
			'memory_key' => 10,
			'title' => 7,
			'summary' => 5,
			'tags' => 5,
			'entity_refs' => 5,
			'memory_subtype' => 4,
			'scope_ref' => 3,
			'content' => 1,
		];

		$score = 0;

		foreach($tokens as $token) {
			foreach($weights as $field => $weight) {
				$text = $fieldTexts[$field] ?? '';

				if($text === '' || strpos($text, $token) === false) {
					continue;
				}

				$score += $weight;
			}
		}

		$phrase = trim($this->normalizeSearchText(implode(' ', $tokens)));

		if($phrase !== '') {
			if($fieldTexts['memory_key'] !== '' && strpos($fieldTexts['memory_key'], $phrase) !== false) {
				$score += 8;
			}
			if($fieldTexts['title'] !== '' && strpos($fieldTexts['title'], $phrase) !== false) {
				$score += 6;
			}
			if($fieldTexts['summary'] !== '' && strpos($fieldTexts['summary'], $phrase) !== false) {
				$score += 4;
			}
			if($fieldTexts['content'] !== '' && strpos($fieldTexts['content'], $phrase) !== false) {
				$score += 2;
			}
		}

		return $score;
	}

	/**
	 * Tokenizes a natural-language query for soft PHP ranking.
	 *
	 * @return array<int,string>
	 */
	protected function tokenizeSearchQuery(string $query): array {
		$query = $this->normalizeSearchText($query);

		if($query === '') {
			return [];
		}

		$parts = preg_split('/\s+/u', $query) ?: [];
		$tokens = [];

		foreach($parts as $part) {
			$part = trim((string)$part);

			if($part === '') {
				continue;
			}

			$length = function_exists('mb_strlen') ? mb_strlen($part) : strlen($part);

			if($length < 2 && !ctype_digit($part)) {
				continue;
			}

			$tokens[$part] = $part;
		}

		return array_values($tokens);
	}

	/**
	 * Converts mixed field values into normalized search text.
	 */
	protected function stringifySearchValue(mixed $value): string {
		if($value === null || $value === '') {
			return '';
		}

		if(is_array($value)) {
			$parts = [];

			array_walk_recursive($value, function(mixed $item) use (&$parts): void {
				if(!is_scalar($item) && $item !== null) {
					return;
				}

				$item = trim((string)$item);

				if($item === '') {
					return;
				}

				$parts[] = $item;
			});

			return $this->normalizeSearchText(implode(' ', $parts));
		}

		return $this->normalizeSearchText((string)$value);
	}

	/**
	 * Normalizes natural-language search text for weak PHP ranking.
	 */
	protected function normalizeSearchText(string $value): string {
		$value = trim($value);

		if($value === '') {
			return '';
		}

		$normalized = preg_replace('/[^\p{L}\p{N}\-_]+/u', ' ', $value);

		if(is_string($normalized)) {
			$value = $normalized;
		}

		$normalized = preg_replace('/\s+/u', ' ', $value);

		if(is_string($normalized)) {
			$value = $normalized;
		}

		$value = trim($value);

		if($value === '') {
			return '';
		}

		if(function_exists('mb_strtolower')) {
			return mb_strtolower($value);
		}

		return strtolower($value);
	}

	/**
	 * Builds a generic WHERE clause from supported filters.
	 */
	protected function buildWhereClause(array $filters, bool $withIdentity = true): string {
		$conditions = [];

		if(!(bool)($filters['include_deleted'] ?? false)) {
			$conditions[] = '`is_deleted` = 0';
		}

		if($withIdentity) {
			$conditions = array_merge($conditions, $this->buildIdentityConditions(true));
		}

		$conditions = array_merge($conditions, $this->buildFilterConditions($filters));

		if($conditions === []) {
			return '';
		}

		return ' WHERE ' . implode(' AND ', $conditions);
	}

	/**
	 * Builds filter conditions for search and list operations.
	 *
	 * Supported filters:
	 * - memory_type
	 * - memory_key
	 * - memory_subtype
	 * - status
	 * - source
	 * - scope
	 * - scope_ref
	 * - ident
	 * - userid
	 * - session
	 * - is_locked
	 * - is_deleted
	 * - created_by
	 * - updated_by
	 * - tags
	 * - entity_refs
	 * - valid_at
	 * - not_expired
	 * - min_confidence
	 */
	protected function buildFilterConditions(array $filters): array {
		$conditions = [];

		if(array_key_exists('memory_type', $filters) && $filters['memory_type'] !== null && $filters['memory_type'] !== '') {
			$memoryTypes = is_array($filters['memory_type']) ? $filters['memory_type'] : [$filters['memory_type']];
			$quoted = [];

			foreach($memoryTypes as $memoryType) {
				$memoryType = trim((string)$memoryType);

				if($memoryType === '') {
					continue;
				}

				$this->assertValidMemoryType($memoryType);
				$quoted[] = $this->quote($memoryType);
			}

			if($quoted !== []) {
				$conditions[] = count($quoted) === 1
					? '`memory_type` = ' . $quoted[0]
					: '`memory_type` IN (' . implode(',', $quoted) . ')';
			}
		}

		if(array_key_exists('memory_key', $filters)) {
			if($filters['memory_key'] === null || $filters['memory_key'] === '') {
				$conditions[] = '`memory_key` IS NULL';
			}
			else {
				$conditions[] = '`memory_key` = ' . $this->quote(trim((string)$filters['memory_key']));
			}
		}

		if(array_key_exists('memory_subtype', $filters) && $filters['memory_subtype'] !== null && $filters['memory_subtype'] !== '') {
			$subtypes = is_array($filters['memory_subtype']) ? $filters['memory_subtype'] : [$filters['memory_subtype']];
			$quoted = [];

			foreach($subtypes as $subtype) {
				$subtype = trim((string)$subtype);

				if($subtype === '') {
					continue;
				}

				$quoted[] = $this->quote($subtype);
			}

			if($quoted !== []) {
				$conditions[] = count($quoted) === 1
					? '`memory_subtype` = ' . $quoted[0]
					: '`memory_subtype` IN (' . implode(',', $quoted) . ')';
			}
		}

		if(array_key_exists('status', $filters) && $filters['status'] !== null && $filters['status'] !== '') {
			$statuses = is_array($filters['status']) ? $filters['status'] : [$filters['status']];
			$quoted = [];

			foreach($statuses as $status) {
				$status = trim((string)$status);

				if($status === '') {
					continue;
				}

				$quoted[] = $this->quote($status);
			}

			if($quoted !== []) {
				$conditions[] = count($quoted) === 1
					? '`status` = ' . $quoted[0]
					: '`status` IN (' . implode(',', $quoted) . ')';
			}
		}

		if(isset($filters['source']) && $filters['source'] !== '') {
			$conditions[] = '`source` = ' . $this->quote((string)$filters['source']);
		}

		if(isset($filters['scope']) && $filters['scope'] !== '') {
			$conditions[] = '`scope` = ' . $this->quote((string)$filters['scope']);
		}

		if(array_key_exists('scope_ref', $filters)) {
			if($filters['scope_ref'] === null || $filters['scope_ref'] === '') {
				$conditions[] = '`scope_ref` IS NULL';
			}
			else {
				$conditions[] = '`scope_ref` = ' . $this->quote((string)$filters['scope_ref']);
			}
		}

		if(isset($filters['ident']) && $filters['ident'] !== '') {
			$conditions[] = '`ident` = ' . $this->quote((string)$filters['ident']);
		}

		if(array_key_exists('userid', $filters)) {
			if($filters['userid'] === null || $filters['userid'] === '') {
				$conditions[] = '`userid` IS NULL';
			}
			else {
				$conditions[] = '`userid` = ' . $this->quote((string)$filters['userid']);
			}
		}

		if(array_key_exists('session', $filters)) {
			if($filters['session'] === null || $filters['session'] === '') {
				$conditions[] = '`session` IS NULL';
			}
			else {
				$conditions[] = '`session` = ' . $this->quote((string)$filters['session']);
			}
		}

		if(array_key_exists('is_locked', $filters)) {
			$conditions[] = '`is_locked` = ' . $this->boolInt($filters['is_locked']);
		}

		if(array_key_exists('is_deleted', $filters)) {
			$conditions[] = '`is_deleted` = ' . $this->boolInt($filters['is_deleted']);
		}

		if(isset($filters['created_by']) && $filters['created_by'] !== '') {
			$conditions[] = '`created_by` = ' . $this->quote((string)$filters['created_by']);
		}

		if(isset($filters['updated_by']) && $filters['updated_by'] !== '') {
			$conditions[] = '`updated_by` = ' . $this->quote((string)$filters['updated_by']);
		}

		if(isset($filters['min_confidence']) && $filters['min_confidence'] !== null && $filters['min_confidence'] !== '') {
			$conditions[] = '`confidence` IS NOT NULL AND `confidence` >= ' . (float)$filters['min_confidence'];
		}

		if(!empty($filters['tags']) && is_array($filters['tags'])) {
			$tagConditions = [];

			foreach($filters['tags'] as $tag) {
				$tag = trim((string)$tag);

				if($tag === '') {
					continue;
				}

				$tagConditions[] = '`tags_json` LIKE ' . $this->quote('%' . $tag . '%');
			}

			if($tagConditions !== []) {
				$conditions[] = '(' . implode(' AND ', $tagConditions) . ')';
			}
		}

		if(!empty($filters['entity_refs']) && is_array($filters['entity_refs'])) {
			$entityConditions = [];

			foreach($filters['entity_refs'] as $ref) {
				$ref = trim((string)$ref);

				if($ref === '') {
					continue;
				}

				$entityConditions[] = '`entity_refs_json` LIKE ' . $this->quote('%' . $ref . '%');
			}

			if($entityConditions !== []) {
				$conditions[] = '(' . implode(' AND ', $entityConditions) . ')';
			}
		}

		if(!empty($filters['valid_at'])) {
			$validAt = $this->quote((string)$filters['valid_at']);

			$conditions[] = '(`valid_from` IS NULL OR `valid_from` <= ' . $validAt . ')';
			$conditions[] = '(`valid_to` IS NULL OR `valid_to` >= ' . $validAt . ')';
		}

		if((bool)($filters['not_expired'] ?? false)) {
			$conditions[] = '(`expires_at` IS NULL OR `expires_at` > NOW())';
		}

		return $conditions;
	}

	/**
	 * Builds identity restrictions for current user/session context.
	 *
	 * If both session and user identities exist, both are allowed.
	 * If only a session exists, only session entries are allowed.
	 */
	protected function buildIdentityConditions(bool $includeSessionAndUser): array {
		$ids = $this->getCurrentIdentityRefs();

		$idents = [];

		if($ids['session_ident'] !== null && $ids['session_ident'] !== '') {
			$idents[] = $this->quote($ids['session_ident']);
		}

		if($includeSessionAndUser && $ids['user_ident'] !== null && $ids['user_ident'] !== '') {
			$idents[] = $this->quote($ids['user_ident']);
		}

		if(!$includeSessionAndUser && $ids['user_ident'] !== null && $ids['user_ident'] !== '') {
			$idents = [$this->quote($ids['user_ident'])];
		}

		if($idents === []) {
			return ['1=0'];
		}

		return [
			'`ident` IN (' . implode(',', $idents) . ')'
		];
	}

	/**
	 * Merges session and user entries for the current identity.
	 *
	 * User entries override session entries for the same logical slot.
	 *
	 * @param array<int,array<string,mixed>> $rows
	 * @return array<int,array<string,mixed>>
	 */
	protected function mergeIdentityRows(array $rows): array {
		if($rows === []) {
			return [];
		}

		$merged = [];

		foreach($rows as $row) {
			$key = $this->buildLogicalMergeKey($row);

			if(!isset($merged[$key])) {
				$merged[$key] = $row;
				continue;
			}

			if($this->shouldOverrideMergedEntry($merged[$key], $row)) {
				$merged[$key] = $row;
			}
		}

		return array_values($merged);
	}

	/**
	 * Builds a logical merge key for current-identity override behavior.
	 *
	 * This is intentionally not the physical row identity.
	 * It represents the "same knowledge slot" across session and user scope.
	 */
	protected function buildLogicalMergeKey(array $row): string {
		$memoryKey = trim((string)($row['memory_key'] ?? ''));

		if($memoryKey !== '') {
			return implode('|', [
				(string)($row['memory_type'] ?? ''),
				$this->normalizeKeyPart($memoryKey),
				(string)($row['scope_ref'] ?? ''),
			]);
		}

		return implode('|', [
			(string)($row['memory_type'] ?? ''),
			(string)($row['memory_subtype'] ?? ''),
			(string)($row['scope_ref'] ?? ''),
			$this->normalizeKeyPart((string)($row['title'] ?? '')),
		]);
	}

	/**
	 * Returns true if the candidate should replace the existing merged row.
	 */
	protected function shouldOverrideMergedEntry(array $existing, array $candidate): bool {
		$existingScope = (string)($existing['scope'] ?? '');
		$candidateScope = (string)($candidate['scope'] ?? '');

		if($existingScope === 'session' && $candidateScope === 'user') {
			return true;
		}

		if($existingScope !== 'user' && $candidateScope === 'user') {
			return true;
		}

		$existingPriority = (int)($existing['priority'] ?? 0);
		$candidatePriority = (int)($candidate['priority'] ?? 0);

		if($candidatePriority > $existingPriority) {
			return true;
		}

		$existingUpdated = strtotime((string)($existing['updated_at'] ?? '')) ?: 0;
		$candidateUpdated = strtotime((string)($candidate['updated_at'] ?? '')) ?: 0;

		return $candidateUpdated > $existingUpdated;
	}

	/**
	 * Checks whether the current identity may access the given entry.
	 */
	protected function canAccessEntry(array $entry): bool {
		$ids = $this->getCurrentIdentityRefs();
		$ident = (string)($entry['ident'] ?? '');

		if($ident === '') {
			return false;
		}

		if($ids['user_ident'] !== null && $ident === $ids['user_ident']) {
			return true;
		}

		if($ids['session_ident'] !== null && $ident === $ids['session_ident']) {
			return true;
		}

		return false;
	}

	/**
	 * Resolves final scope for write operations.
	 *
	 * If "user" is requested but no authenticated user exists, session is used.
	 * If no scope is given, user is preferred and session is used as fallback.
	 */
	protected function resolveFinalScope(string $requestedScope): string {
		$requestedScope = strtolower(trim($requestedScope));
		$requestedScope = ($requestedScope === 'user' || $requestedScope === 'session') ? $requestedScope : '';

		$this->session->start();
		$this->accesscontrol->authenticate();

		$userid = $this->accesscontrol->getUserId();
		$hasUser = ($userid !== null && (string)$userid !== '');

		if($requestedScope === 'session') {
			return 'session';
		}

		if($requestedScope === 'user') {
			return $hasUser ? 'user' : 'session';
		}

		return $hasUser ? 'user' : 'session';
	}

	/**
	 * Resolves identity fields for a given scope.
	 *
	 * @return array{userid:?string,session:?string,ident:string}
	 */
	protected function resolveScopeIds(string $scope): array {
		$this->session->start();
		$this->accesscontrol->authenticate();

		$sessionKey = (string)$this->session->getId();
		$userid = $this->accesscontrol->getUserId();
		$useridStr = $userid !== null ? (string)$userid : null;

		if($scope === 'user') {
			$ident = $this->buildIdent('user', $useridStr, $sessionKey);

			return [
				'userid' => $useridStr,
				'session' => $sessionKey !== '' ? $sessionKey : null,
				'ident' => $ident,
			];
		}

		$ident = $this->buildIdent('session', $useridStr, $sessionKey);

		return [
			'userid' => null,
			'session' => $sessionKey !== '' ? $sessionKey : null,
			'ident' => $ident,
		];
	}

	/**
	 * Returns current session/user identity references.
	 *
	 * @return array{userid:?string,session:?string,user_ident:?string,session_ident:?string}
	 */
	protected function getCurrentIdentityRefs(): array {
		$this->session->start();
		$this->accesscontrol->authenticate();

		$sessionKey = (string)$this->session->getId();
		$userid = $this->accesscontrol->getUserId();
		$useridStr = $userid !== null && (string)$userid !== '' ? (string)$userid : null;

		return [
			'userid' => $useridStr,
			'session' => $sessionKey !== '' ? $sessionKey : null,
			'user_ident' => $useridStr !== null ? $this->buildIdent('user', $useridStr, $sessionKey) : null,
			'session_ident' => $sessionKey !== '' ? $this->buildIdent('session', $useridStr, $sessionKey) : null,
		];
	}

	/**
	 * Builds a stable identity key for user or session scope.
	 */
	protected function buildIdent(string $scope, ?string $userid, string $sessionKey): string {
		if($scope === 'user') {
			if($userid === null || $userid === '') {
				return 's:' . $sessionKey;
			}

			return 'u:' . $userid;
		}

		return 's:' . $sessionKey;
	}

	/**
	 * Converts database rows into hydrated entries.
	 *
	 * @param array<int,array<string,mixed>> $rows
	 * @return array<int,array<string,mixed>>
	 */
	protected function hydrateRows(array $rows): array {
		$result = [];

		foreach($rows as $row) {
			$result[] = $this->hydrateRow($row);
		}

		return $result;
	}

	/**
	 * Decodes JSON fields and normalizes scalar values.
	 *
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	protected function hydrateRow(array $row): array {
		$row['id'] = isset($row['id']) ? (int)$row['id'] : null;
		$row['priority'] = isset($row['priority']) ? (int)$row['priority'] : 0;
		$row['is_locked'] = isset($row['is_locked']) ? (int)$row['is_locked'] : 0;
		$row['is_mutable_by_llm'] = isset($row['is_mutable_by_llm']) ? (int)$row['is_mutable_by_llm'] : 1;
		$row['is_deletable_by_llm'] = isset($row['is_deletable_by_llm']) ? (int)$row['is_deletable_by_llm'] : 1;
		$row['is_deleted'] = isset($row['is_deleted']) ? (int)$row['is_deleted'] : 0;
		$row['confidence'] = $row['confidence'] !== null ? (float)$row['confidence'] : null;

		$row['tags_json'] = $this->decodeJsonField($row['tags_json'] ?? null);
		$row['entity_refs_json'] = $this->decodeJsonField($row['entity_refs_json'] ?? null);
		$row['meta_json'] = $this->decodeJsonField($row['meta_json'] ?? null);

		return $row;
	}

	/**
	 * Decodes a JSON field into PHP data.
	 */
	protected function decodeJsonField(mixed $value): mixed {
		if($value === null || $value === '') {
			return null;
		}

		if(is_array($value)) {
			return $value;
		}

		$decoded = json_decode((string)$value, true);

		if(json_last_error() !== JSON_ERROR_NONE) {
			return null;
		}

		return $decoded;
	}

	/**
	 * Throws if the given memory type is not supported.
	 */
	protected function assertValidMemoryType(string $memoryType): void {
		if(!in_array($memoryType, $this->memoryTypes, true)) {
			throw new \InvalidArgumentException('Unsupported memory type: ' . $memoryType);
		}
	}

	/**
	 * Returns an SQL-safe quoted string.
	 */
	protected function quote(string $value): string {
		$this->db->connect();

		return "'" . $this->db->escape($value) . "'";
	}

	/**
	 * Returns an SQL fragment for nullable strings.
	 */
	protected function nullableString(mixed $value): string {
		if($value === null || $value === '') {
			return 'NULL';
		}

		return $this->quote((string)$value);
	}

	/**
	 * Returns an SQL fragment for nullable datetime values.
	 */
	protected function nullableDateTime(mixed $value): string {
		if($value === null || $value === '') {
			return 'NULL';
		}

		return $this->quote((string)$value);
	}

	/**
	 * Returns an SQL fragment for nullable decimal values.
	 */
	protected function nullableDecimal(mixed $value): string {
		if($value === null || $value === '') {
			return 'NULL';
		}

		return (string)(float)$value;
	}

	/**
	 * Returns an SQL fragment for nullable JSON values.
	 */
	protected function nullableJson(mixed $value): string {
		if($value === null || $value === '') {
			return 'NULL';
		}

		if(is_string($value)) {
			return $this->quote($value);
		}

		$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if($json === false) {
			throw new \InvalidArgumentException('Failed to encode JSON field');
		}

		return $this->quote($json);
	}

	/**
	 * Converts a mixed value into an SQL-compatible boolean integer.
	 */
	protected function boolInt(mixed $value): int {
		return $value ? 1 : 0;
	}

	/**
	 * Converts a datetime string to a Unix timestamp.
	 */
	protected function toTimestamp(mixed $value): ?int {
		if($value === null || $value === '') {
			return null;
		}

		$timestamp = strtotime((string)$value);

		if($timestamp === false) {
			return null;
		}

		return $timestamp;
	}

	/**
	 * Truncates text to a maximum length.
	 */
	protected function truncate(string $text, int $maxLength): string {
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

	/**
	 * Normalizes a key-like text part for logical comparisons.
	 */
	protected function normalizeKeyPart(string $value): string {
		$value = trim($value);

		if($value === '') {
			return '';
		}

		if(function_exists('mb_strtolower')) {
			return mb_strtolower($value);
		}

		return strtolower($value);
	}

	/**
	 * Builds the SQL expression for injectable flag filtering.
	 */
	protected function buildInjectFlagSqlCondition(bool $mustBeInjectable): string {
		$alwaysInject = $this->buildJsonBoolTrueCondition('always_inject');
		$pinned = $this->buildJsonBoolTrueCondition('pinned');
		$injectable = '(' . $alwaysInject . ' OR ' . $pinned . ')';

		if($mustBeInjectable) {
			return $injectable;
		}

		return '(NOT ' . $injectable . ')';
	}

	/**
	 * Builds an SQL condition that checks whether a JSON boolean-like field is true.
	 */
	protected function buildJsonBoolTrueCondition(string $field): string {
		$expr = 'JSON_UNQUOTE(JSON_EXTRACT(`meta_json`, ' . $this->quote('$.' . $field) . '))';

		return '(' . $expr . ' IN (\'1\',\'true\',\'TRUE\'))';
	}

	/**
	 * Builds SQL ORDER BY fragment for curated injection loading.
	 */
	protected function buildCuratedOrderSql(): string {
		$injectOrderExpr = 'COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(`meta_json`, ' . $this->quote('$.inject_order') . ')) AS SIGNED), 100)';

		return $injectOrderExpr . ' ASC, `priority` DESC, `updated_at` DESC, `id` DESC';
	}

	/**
	 * Sorts hydrated entries using curated injection ordering.
	 *
	 * @param array<int,array<string,mixed>> $entries
	 * @return array<int,array<string,mixed>>
	 */
	protected function sortCuratedEntries(array $entries): array {
		usort($entries, function(array $a, array $b): int {
			$aOrder = $this->getInjectOrder($a);
			$bOrder = $this->getInjectOrder($b);

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

			if($aUpdated !== $bUpdated) {
				return $bUpdated <=> $aUpdated;
			}

			return (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0);
		});

		return $entries;
	}

	/**
	 * Returns the numeric inject order for one entry.
	 */
	protected function getInjectOrder(array $entry): int {
		$meta = is_array($entry['meta_json'] ?? null) ? $entry['meta_json'] : [];

		if(!array_key_exists('inject_order', $meta)) {
			return 100;
		}

		return (int)$meta['inject_order'];
	}

	/**
	 * Converts a mixed value into a strict SQL-bool style flag.
	 */
	protected function toSqlBool(mixed $value): bool {
		if(is_bool($value)) {
			return $value;
		}

		if(is_int($value)) {
			return $value !== 0;
		}

		$value = strtolower(trim((string)$value));

		return in_array($value, ['1', 'true', 'yes', 'on'], true);
	}
}
