<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\NoVectorStoreAgentResource;

/**
 * @covers \MissionBay\Resource\NoVectorStoreAgentResource
 */
class NoVectorStoreAgentResourceTest extends TestCase {

	public function testGetName(): void {
		$this->assertSame('novectorstoreagentresource', NoVectorStoreAgentResource::getName());
	}

	public function testDescription(): void {
		$r = new NoVectorStoreAgentResource('vs1');
		$this->assertSame(
			'A no-operation vector store that does not store or retrieve vectors.',
			$r->getDescription()
		);
	}

	public function testUpsertIsNoOpAndDoesNotThrow(): void {
		$r = new NoVectorStoreAgentResource('vs2');

		$r->upsert(
			id: 'id1',
			vector: [0.1, 0.2, 0.3],
			text: 'hello',
			hash: 'hash1',
			metadata: ['k' => 'v']
		);

		$this->assertTrue(true); // reached here
	}

	public function testExistsByHashAlwaysFalse(): void {
		$r = new NoVectorStoreAgentResource('vs3');

		$this->assertFalse($r->existsByHash('anything'));
		$this->assertFalse($r->existsByHash(''));
		$this->assertFalse($r->existsByHash('hash1'));
	}

	public function testSearchAlwaysEmpty(): void {
		$r = new NoVectorStoreAgentResource('vs4');

		$this->assertSame([], $r->search([0.1, 0.2, 0.3]));
		$this->assertSame([], $r->search([], 10, 0.1));
		$this->assertSame([], $r->search([1.0], 0, null));
	}

	public function testCreateCollectionIsNoOpAndDoesNotThrow(): void {
		$r = new NoVectorStoreAgentResource('vs5');

		$r->createCollection();

		$this->assertTrue(true);
	}

	public function testDeleteCollectionIsNoOpAndDoesNotThrow(): void {
		$r = new NoVectorStoreAgentResource('vs6');

		$r->deleteCollection();

		$this->assertTrue(true);
	}

	public function testGetInfoReturnsExpectedStaticStructure(): void {
		$r = new NoVectorStoreAgentResource('vs7');

		$info = $r->getInfo();

		$this->assertIsArray($info);

		$this->assertSame('no-op', $info['type'] ?? null);
		$this->assertArrayHasKey('collection', $info);
		$this->assertNull($info['collection']);

		$this->assertSame(0, $info['count'] ?? null);
		$this->assertSame([], $info['ids'] ?? null);

		$this->assertIsArray($info['details'] ?? null);
		$this->assertFalse($info['details']['persistent'] ?? true);
		$this->assertSame(
			'This vector store does not store or return any data.',
			$info['details']['description'] ?? null
		);
	}
}
