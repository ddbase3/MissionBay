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

namespace Base3\MissionBay\Test\Orchestrator;

use Base3\AssistantFoundation\Api\IAgentToolResultCache;
use Base3\AssistantFoundation\Dto\AgentToolCacheEntry;

/** In-memory recording double for exercising the real cache-stage and cache-service code. */
final class AgentToolCacheCoverageRecordingCache implements IAgentToolResultCache {

	public bool $available = true;
	public bool $failGet = false;
	public bool $failPut = false;
	public array $entries = [];
	public array $readKeys = [];
	public array $writeTtls = [];
	public array $deletedKeys = [];

	public function isAvailable(): bool {
		return $this->available;
	}

	public function get(string $key): ?AgentToolCacheEntry {
		$this->readKeys[] = $key;
		if ($this->failGet) {
			throw new \RuntimeException('Cache backend unavailable');
		}
		return $this->entries[$key] ?? null;
	}

	public function put(string $key, AgentToolCacheEntry $entry, int $ttlSeconds): void {
		if ($this->failPut) {
			throw new \RuntimeException('Cache backend is read-only');
		}
		$this->writeTtls[$key] = $ttlSeconds;
		$this->entries[$key] = $entry;
	}

	public function delete(string $key): bool {
		$this->deletedKeys[] = $key;
		$exists = isset($this->entries[$key]);
		unset($this->entries[$key]);
		return $exists;
	}
}
