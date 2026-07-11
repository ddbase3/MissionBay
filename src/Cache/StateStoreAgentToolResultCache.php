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

namespace MissionBay\Cache;

use AssistantFoundation\Api\IAgentToolResultCache;
use AssistantFoundation\Dto\AgentToolCacheEntry;
use Base3\State\Api\IStateStore;

/**
 * StateStoreAgentToolResultCache
 *
 * Uses the shared BASE3 state store as a TTL-capable cache backend. Cache
 * entries are disposable and namespaced independently from other runtime state.
 */
final class StateStoreAgentToolResultCache implements IAgentToolResultCache {

	public function isAvailable(): bool {
		return true;
	}


	private const PREFIX = 'missionbay.agent_tool_result_cache.';

	public function __construct(
		private readonly IStateStore $stateStore
	) {}

	public function get(string $key): ?AgentToolCacheEntry {
		$value = $this->stateStore->get($this->stateKey($key));

		if (!is_array($value)) {
			return null;
		}

		try {
			return AgentToolCacheEntry::fromArray($value);
		} catch (\Throwable $e) {
			$this->stateStore->delete($this->stateKey($key));
			return null;
		}
	}

	public function put(string $key, AgentToolCacheEntry $entry, int $ttlSeconds): void {
		if ($ttlSeconds < 1) {
			throw new \InvalidArgumentException('Tool-cache TTL must be greater than zero.');
		}

		$this->stateStore->set(
			$this->stateKey($key),
			$entry->toArray(),
			$ttlSeconds
		);
	}

	public function delete(string $key): bool {
		return $this->stateStore->delete($this->stateKey($key));
	}

	private function stateKey(string $key): string {
		return self::PREFIX . hash('sha256', $key);
	}
}
