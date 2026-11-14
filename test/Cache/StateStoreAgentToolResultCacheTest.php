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
use Base3\CoreFoundation\State\Api\IStateStore;
use Base3\MissionBay\Cache\StateStoreAgentToolResultCache;
use PHPUnit\Framework\TestCase;

final class StateStoreAgentToolResultCacheTest extends TestCase {

	public function testExistingEntryCanBeRestoredWithoutLosingMetadata(): void {
		$entry = $this->entry();
		$state = $this->createStub(IStateStore::class);
		$state->method('get')->willReturn($entry->toArray());
		$cache = new StateStoreAgentToolResultCache($state);
		$this->assertTrue($cache->isAvailable());
		$this->assertSame($entry->toArray(), $cache->get('any-key')?->toArray());
	}

	public function testMissingOrNonArrayEntryIsACacheMiss(): void {
		foreach ([null, false, 'broken', 42] as $value) {
			$state = $this->createStub(IStateStore::class);
			$state->method('get')->willReturn($value);
			$this->assertNull((new StateStoreAgentToolResultCache($state))->get('key'));
		}
	}

	public function testCorruptSerializedEntryIsDeletedAndNeverReturned(): void {
		$key = 'missionbay.agent_tool_result_cache.' . hash('sha256', 'private-key');
		$state = $this->createMock(IStateStore::class);
		$state->expects($this->once())->method('get')->with($key)->willReturn(['tool' => 'missing-identity']);
		$state->expects($this->once())->method('delete')->with($key)->willReturn(true);
		$this->assertNull((new StateStoreAgentToolResultCache($state))->get('private-key'));
	}

	public function testPutNamespacesAndHashesKeyAndPropagatesTtl(): void {
		$entry = $this->entry();
		$key = 'missionbay.agent_tool_result_cache.' . hash('sha256', 'scope\nprivate');
		$state = $this->createMock(IStateStore::class);
		$state->expects($this->once())->method('set')->with($key, $entry->toArray(), 120);
		(new StateStoreAgentToolResultCache($state))->put('scope\nprivate', $entry, 120);
	}

	public function testZeroOrNegativeTtlIsRejectedBeforeWrite(): void {
		$entry = $this->entry();
		$state = $this->createMock(IStateStore::class);
		$state->expects($this->never())->method('set');
		$cache = new StateStoreAgentToolResultCache($state);
		foreach ([0, -10] as $ttl) {
			try {
				$cache->put('key', $entry, $ttl);
				$this->fail('Cache must reject a nonpositive TTL.');
			} catch (\InvalidArgumentException $e) {
				$this->assertStringContainsString('TTL', $e->getMessage());
			}
		}
	}

	public function testDeleteForwardsBackendResultWithHashedKey(): void {
		$key = 'missionbay.agent_tool_result_cache.' . hash('sha256', 'key');
		$state = $this->createMock(IStateStore::class);
		$state->expects($this->once())->method('delete')->with($key)->willReturn(false);
		$this->assertFalse((new StateStoreAgentToolResultCache($state))->delete('key'));
	}

	private function entry(): AgentToolCacheEntry {
		return new AgentToolCacheEntry('identity', 'read', 'hash', 'scope', ['output' => 1], '2026-01-01', '2026-01-02', ['source' => 'tool']);
	}
}
