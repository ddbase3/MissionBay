<?php declare(strict_types=1);

namespace MissionBay\Test\Cache;

use AssistantFoundation\Dto\AgentToolCacheEntry;
use MissionBay\Cache\NullAgentToolResultCache;
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
