<?php declare(strict_types=1);

/**
 * Filename: plugin/MissionBay/test/Resource/NoVectorStoreAgentResourceTest.php
 */

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\NoVectorStoreAgentResource;
use MissionBay\Dto\AgentEmbeddingChunk;

/**
 * @covers \MissionBay\Resource\NoVectorStoreAgentResource
 */
class NoVectorStoreAgentResourceTest extends TestCase {

	private function makeChunk(
		string $collectionKey,
		string $id,
		array $vector,
		string $text,
		string $hash,
		array $metadata = [],
		int $chunkIndex = 0
	): AgentEmbeddingChunk {
		$ref = new \ReflectionClass(AgentEmbeddingChunk::class);

		/** @var AgentEmbeddingChunk $chunk */
		$chunk = $ref->newInstanceWithoutConstructor();

		// Set only properties that exist (DTO may evolve)
		$props = [
			'collectionKey' => $collectionKey,
			'id' => $id,
			'vector' => $vector,
			'text' => $text,
			'hash' => $hash,
			'metadata' => $metadata,
			'chunkIndex' => $chunkIndex,
		];

		foreach ($props as $name => $value) {
			if ($ref->hasProperty($name)) {
				$p = $ref->getProperty($name);
				$p->setAccessible(true);
				$p->setValue($chunk, $value);
			}
		}

		return $chunk;
	}

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

		$r->upsert($this->makeChunk(
			collectionKey: 'c',
			id: 'id1',
			vector: [0.1, 0.2, 0.3],
			text: 'hello',
			hash: 'hash1',
			metadata: ['k' => 'v'],
			chunkIndex: 0
		));

		$this->assertTrue(true);
	}

	public function testExistsByHashAlwaysFalse(): void {
		$r = new NoVectorStoreAgentResource('vs3');

		$this->assertFalse($r->existsByHash('c', 'anything'));
		$this->assertFalse($r->existsByHash('c', ''));
		$this->assertFalse($r->existsByHash('c', 'hash1'));
	}

	public function testExistsByFilterAlwaysFalse(): void {
		$r = new NoVectorStoreAgentResource('vs3b');

		$this->assertFalse($r->existsByFilter('c', ['hash' => 'x']));
		$this->assertFalse($r->existsByFilter('c', []));
	}

	public function testDeleteByFilterAlwaysZero(): void {
		$r = new NoVectorStoreAgentResource('vs3c');

		$this->assertSame(0, $r->deleteByFilter('c', ['hash' => 'x']));
		$this->assertSame(0, $r->deleteByFilter('c', []));
	}

	public function testSearchAlwaysEmpty(): void {
		$r = new NoVectorStoreAgentResource('vs4');

		$this->assertSame([], $r->search('c', [0.1, 0.2, 0.3]));
		$this->assertSame([], $r->search('c', [], 10, 0.1));
		$this->assertSame([], $r->search('c', [1.0], 0, null));
	}

	public function testCreateCollectionIsNoOpAndDoesNotThrow(): void {
		$r = new NoVectorStoreAgentResource('vs5');

		$r->createCollection('c');

		$this->assertTrue(true);
	}

	public function testDeleteCollectionIsNoOpAndDoesNotThrow(): void {
		$r = new NoVectorStoreAgentResource('vs6');

		$r->deleteCollection('c');

		$this->assertTrue(true);
	}

	public function testGetInfoReturnsExpectedStaticStructure(): void {
		$r = new NoVectorStoreAgentResource('vs7');

		$info = $r->getInfo('c');

		$this->assertIsArray($info);

		$this->assertSame('no-op', $info['type'] ?? null);
		$this->assertSame('c', $info['collection_key'] ?? null);
		$this->assertArrayHasKey('collection', $info);
		$this->assertNull($info['collection']);

		$this->assertSame(0, $info['count'] ?? null);

		$this->assertIsArray($info['details'] ?? null);
		$this->assertFalse($info['details']['persistent'] ?? true);
		$this->assertSame(
			'This vector store does not store or return any data.',
			$info['details']['description'] ?? null
		);
	}
}
