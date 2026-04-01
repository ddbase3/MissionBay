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

namespace MissionBay\Api;

use Base3\Api\IBase;

/**
 * Interface for persistent agent knowledge storage.
 *
 * This service stores and retrieves long-lived knowledge entries such as:
 * - task memory
 * - episodic memory
 * - semantic memory
 * - procedural memory
 *
 * In contrast to IAgentMemory, which is intended for runtime/dialog memory,
 * this interface is intended for persistent, queryable knowledge records.
 */
interface IAgentKnowledgeService extends IBase {

	/**
	 * Returns the supported top-level memory types.
	 *
	 * Example values:
	 * - task
	 * - episodic
	 * - semantic
	 * - procedural
	 *
	 * @return array<string>
	 */
	public function getMemoryTypes(): array;

	/**
	 * Returns the allowed status values for a given memory type.
	 *
	 * Example:
	 * - task => open, in_progress, blocked, resolved, closed, cancelled
	 * - semantic => draft, valid, deprecated, superseded, invalid
	 *
	 * @param string $memoryType Top-level memory type.
	 * @return array<string>
	 */
	public function getAllowedStatuses(string $memoryType): array;

	/**
	 * Creates a new knowledge entry and returns its numeric ID.
	 *
	 * Expected keys in $data may include:
	 * - memory_type         (string, required)
	 * - memory_key          (?string) Stable logical key for safe upsert/merge
	 * - memory_subtype      (?string)
	 * - status              (?string)
	 * - title               (string, required)
	 * - content             (string, required)
	 * - summary             (?string)
	 * - tags_json           (?array)
	 * - entity_refs_json    (?array)
	 * - meta_json           (?array)
	 * - source              (?string)
	 * - scope               (?string)
	 * - scope_ref           (?string)
	 * - is_locked           (?bool)
	 * - is_mutable_by_llm   (?bool)
	 * - is_deletable_by_llm (?bool)
	 * - priority            (?int)
	 * - confidence          (?float)
	 * - valid_from          (?string)
	 * - valid_to            (?string)
	 * - expires_at          (?string)
	 * - created_by          (?string)
	 * - updated_by          (?string)
	 *
	 * @param array $data Structured knowledge entry payload.
	 * @return int Newly created entry ID.
	 */
	public function createEntry(array $data): int;

	/**
	 * Updates an existing knowledge entry.
	 *
	 * The implementation should validate write permissions, lock state,
	 * memory type, and status consistency before applying changes.
	 *
	 * @param int   $id   Entry ID.
	 * @param array $data Fields to update.
	 * @return bool True if the entry was updated, false otherwise.
	 */
	public function updateEntry(int $id, array $data): bool;

	/**
	 * Soft-deletes an existing knowledge entry.
	 *
	 * The implementation should respect lock and delete permissions.
	 *
	 * @param int         $id        Entry ID.
	 * @param string|null $deletedBy Optional actor identifier.
	 * @return bool True if the entry was marked as deleted, false otherwise.
	 */
	public function deleteEntry(int $id, ?string $deletedBy = null): bool;

	/**
	 * Returns a single knowledge entry by ID.
	 *
	 * Deleted entries may be excluded unless explicitly requested.
	 *
	 * @param int  $id             Entry ID.
	 * @param bool $includeDeleted Whether soft-deleted entries should be included.
	 * @return array<string,mixed>|null
	 */
	public function getEntryById(int $id, bool $includeDeleted = false): ?array;

	/**
	 * Returns multiple knowledge entries matching the given filters.
	 *
	 * Supported filters may include:
	 * - memory_type
	 * - memory_key
	 * - memory_subtype
	 * - status
	 * - source
	 * - scope
	 * - scope_ref
	 * - is_locked
	 * - is_deleted
	 * - created_by
	 * - updated_by
	 * - tags
	 * - entity_refs
	 * - valid_at
	 * - not_expired
	 *
	 * @param array $filters Query filters.
	 * @param int   $limit   Maximum number of entries to return.
	 * @param int   $offset  Result offset.
	 * @return array<int,array<string,mixed>>
	 */
	public function findEntries(array $filters = [], int $limit = 50, int $offset = 0): array;

	/**
	 * Performs a free-text and/or semantic search over knowledge entries.
	 *
	 * The concrete implementation may use SQL LIKE, FULLTEXT, embeddings,
	 * vector search, or a hybrid approach.
	 *
	 * Supported options may include:
	 * - memory_type
	 * - memory_key
	 * - memory_subtype
	 * - status
	 * - scope
	 * - scope_ref
	 * - source
	 * - tags
	 * - entity_refs
	 * - min_confidence
	 * - valid_at
	 * - not_expired
	 *
	 * @param string $query   User query or search text.
	 * @param array  $options Search options and filters.
	 * @param int    $limit   Maximum number of results.
	 * @param int    $offset  Result offset.
	 * @return array<int,array<string,mixed>>
	 */
	public function searchEntries(string $query, array $options = [], int $limit = 20, int $offset = 0): array;

	/**
	 * Returns a compact extract of relevant knowledge entries for prompt injection.
	 *
	 * The implementation may internally rank, summarize, and filter entries
	 * before returning them.
	 *
	 * @param string $query   Current user request or task description.
	 * @param array  $options Retrieval and formatting options.
	 * @param int    $limit   Maximum number of source entries to consider.
	 * @return string
	 */
	public function buildPromptExtract(string $query, array $options = [], int $limit = 10): string;

	/**
	 * Updates the last-access timestamp for a knowledge entry.
	 *
	 * @param int $id Entry ID.
	 * @return bool True if the timestamp was updated, false otherwise.
	 */
	public function touchEntry(int $id): bool;

	/**
	 * Returns whether the given status is valid for the given memory type.
	 *
	 * @param string      $memoryType Memory type.
	 * @param string|null $status     Status to validate.
	 * @return bool
	 */
	public function isValidStatusForType(string $memoryType, ?string $status): bool;

	/**
	 * Returns whether an entry may be modified by the LLM.
	 *
	 * @param array<string,mixed> $entry Full entry data.
	 * @return bool
	 */
	public function isMutableByLlm(array $entry): bool;

	/**
	 * Returns whether an entry may be deleted by the LLM.
	 *
	 * @param array<string,mixed> $entry Full entry data.
	 * @return bool
	 */
	public function isDeletableByLlm(array $entry): bool;

	/**
	 * Returns whether an entry is currently valid at the given point in time.
	 *
	 * If $at is null, the current time should be used.
	 *
	 * @param array<string,mixed> $entry Full entry data.
	 * @param string|null         $at    ISO datetime string or null.
	 * @return bool
	 */
	public function isEntryValidAt(array $entry, ?string $at = null): bool;

	/**
	 * Returns whether an entry is expired.
	 *
	 * @param array<string,mixed> $entry Full entry data.
	 * @param string|null         $at    ISO datetime string or null.
	 * @return bool
	 */
	public function isEntryExpired(array $entry, ?string $at = null): bool;
}
