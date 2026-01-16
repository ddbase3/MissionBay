<?php declare(strict_types=1);

namespace MissionBay\Api;

/**
 * IAgentVectorFilter
 *
 * Provides a backend-agnostic FilterSpec for vector retrieval.
 *
 * Design:
 * - A filter resource returns a partial FilterSpec.
 * - RetrievalAgentTool merges all attached filters into one final FilterSpec.
 * - The VectorStore implementation translates FilterSpec to backend-native filters.
 *
 * FilterSpec v1 (minimal, but useful):
 * - must:     array<string,scalar|array>     AND group (array = OR on same key)
 * - any:      array<string,scalar|array>     OR group (at least one must match)
 * - must_not: array<string,scalar|array>     AND NOT group (none must match)
 *
 * Examples:
 * - public=1 AND type_alias="text"
 *   ['must' => ['public' => 1, 'type_alias' => 'text']]
 *
 * - (tag in ["dancephotography","portrait"]) AND NOT archive=1
 *   ['must' => ['tags' => ['dancephotography','portrait']], 'must_not' => ['archive' => 1]]
 *
 * Notes:
 * - Scalars are compared as exact match.
 * - If a payload field is an array (e.g. tags/ref_uuids), scalar match means "contains".
 */
interface IAgentVectorFilter {

	/**
	 * Returns a FilterSpec fragment.
	 *
	 * Implementations should return:
	 * - null => no filtering
	 * - array => FilterSpec fragment in v1 format
	 *
	 * @return array<string,mixed>|null
	 */
	public function getFilterSpec(): ?array;
}
