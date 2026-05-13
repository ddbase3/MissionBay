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

namespace MissionBay\Dto;

/**
 * AgentInfoRequest
 *
 * Compact DTO for read-only information requests.
 *
 * The central info tool normalizes and validates raw tool arguments before
 * creating this object. Providers should treat all fields as already safe
 * runtime input, but may still apply domain-specific interpretation.
 */
class AgentInfoRequest {

	/**
	 * Technical topic name.
	 */
	public string $topic;

	/**
	 * Optional free-text query.
	 */
	public string $query;

	/**
	 * Requested response scope.
	 *
	 * Supported scopes:
	 * - find
	 * - summary
	 * - detail
	 * - link
	 */
	public string $scope;

	/**
	 * Maximum number of list items to return.
	 */
	public int $limit;

	/**
	 * Pagination offset for list-like results.
	 */
	public int $offset;

	public function __construct(
		string $topic = '',
		string $query = '',
		string $scope = 'summary',
		int $limit = 5,
		int $offset = 0
	) {
		$this->topic = $topic;
		$this->query = $query;
		$this->scope = $scope;
		$this->limit = $limit;
		$this->offset = $offset;
	}

	/**
	 * Returns true if this request asks for topic discovery.
	 *
	 * @return bool
	 */
	public function isTopicDiscovery(): bool {
		return $this->topic === 'topics';
	}
}
