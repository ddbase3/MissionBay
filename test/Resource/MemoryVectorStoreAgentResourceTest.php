<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\MemoryVectorStoreAgentResource;

/**
 * @covers \MissionBay\Resource\MemoryVectorStoreAgentResource
 */
class MemoryVectorStoreAgentResourceTest extends TestCase {

	public function testGetName(): void {
		$this->assertSame('memoryvectorstoreagentresource', MemoryVectorStoreAgentResource::getName());
	}

	public function testCreateCollectionClearsStoreAndInfoReflectsEmpty(): void {
		$r = new MemoryVectorStoreAgentResource('vs1');

		$r->upsert('id1', [0.1, 0.2], 't1', 'h1');
		$r->upsert('id2', [0.3, 0.4], 't2', 'h2');

		$r->createCollection();

		$info = $r->getInfo();
		$this->assertSame('memory', $info['type'] ?? null);
		$this->assertSame('in-memory', $info['collection'] ?? null);
		$this->assertSame(0, $info['count'] ?? null);
		$this->assertSame([], $info['ids'] ?? null);

		$details = $info['details'] ?? [];
		$this->assertSame(false, $details['persistent'] ?? null);
		$this->assertIsString($details['description'] ?? null);
	}

	public function testDeleteCollectionClearsStore(): void {
		$r = new MemoryVectorStoreAgentResource('vs2');

		$r->upsert('id1', [0.1], 't1', 'h1');
		$this->assertTrue($r->existsByHash('h1'));

		$r->deleteCollection();

		$this->assertFalse($r->existsByHash('h1'));
		$info = $r->getInfo();
		$this->assertSame(0, $info['count'] ?? null);
	}

	public function testUpsertStoresItemAndExistsByHashFindsIt(): void {
		$r = new MemoryVectorStoreAgentResource('vs3');

		$this->assertFalse($r->existsByHash('hash-x'));

		$r->upsert('original-id-ignored', [1.0, 2.0, 3.0], 'Hello', 'hash-x', ['foo' => 'bar']);

		$this->assertTrue($r->existsByHash('hash-x'));

		$info = $r->getInfo();
		$this->assertSame(1, $info['count'] ?? null);

		$ids = $info['ids'] ?? [];
		$this->assertIsArray($ids);
		$this->assertCount(1, $ids);

		// UUID v4 format check: 8-4-4-4-12 hex
		$this->assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
			(string)$ids[0]
		);
	}

	public function testUpsertIgnoresProvidedIdAndCreatesUniqueUuidKeys(): void {
		$r = new MemoryVectorStoreAgentResource('vs4');

		$r->upsert('same-id', [0.0], 'A', 'h1');
		$r->upsert('same-id', [0.0], 'B', 'h2');

		$info = $r->getInfo();
		$this->assertSame(2, $info['count'] ?? null);

		$ids = $info['ids'] ?? [];
		$this->assertCount(2, $ids);
		$this->assertNotSame($ids[0], $ids[1], 'Expected different UUID keys for each upsert call.');
	}

	public function testExistsByHashFalseForUnknownHash(): void {
		$r = new MemoryVectorStoreAgentResource('vs5');

		$r->upsert('id1', [0.1], 't1', 'h1');

		$this->assertFalse($r->existsByHash('does-not-exist'));
	}

	public function testSearchReturnsEmptyArrayAlways(): void {
		$r = new MemoryVectorStoreAgentResource('vs6');

		$r->upsert('id1', [0.1], 't1', 'h1');

		$this->assertSame([], $r->search([0.1], 3, null));
		$this->assertSame([], $r->search([0.1], 1, 0.5));
	}
}
