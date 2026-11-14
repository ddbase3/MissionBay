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

namespace Base3\MissionBay\Test\Cache;

use Base3\AssistantFoundation\Dto\AgentToolCacheEntry;
use Base3\MissionBay\Cache\NullAgentToolResultCache;
use PHPUnit\Framework\TestCase;

final class NullAgentToolResultCacheTest extends TestCase {

	public function testDisabledCacheNeverReturnsData(): void {
		$cache = new NullAgentToolResultCache();
		$this->assertFalse($cache->isAvailable());
		$this->assertNull($cache->get('key'));
		$this->assertFalse($cache->delete('key'));
	}

	public function testPutDoesNotRetainOutput(): void {
		$cache = new NullAgentToolResultCache();
		$entry = new AgentToolCacheEntry('tool-id', 'read', 'hash', 'scope', ['secret' => 1], 'now', 'future');
		$cache->put('key', $entry, 20);
		$this->assertNull($cache->get('key'));
		$cache->put('key', $entry, 0);
		$this->assertNull($cache->get('key'));
	}
}
