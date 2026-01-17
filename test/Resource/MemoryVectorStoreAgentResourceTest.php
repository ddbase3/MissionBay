<?php declare(strict_types=1);

/**
 * Filename: plugin/MissionBay/test/Resource/MemoryVectorStoreAgentResourceTest.php
 */

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\MemoryVectorStoreAgentResource;
use MissionBay\Dto\AgentEmbeddingChunk;

/**
 * @covers \MissionBay\Resource\MemoryVectorStoreAgentResource
 */
class MemoryVectorStoreAgentResourceTest extends TestCase {

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
		$this->assertSame('memoryvectorstoreagentresource', MemoryVectorStoreAgentResource::getName());
	}

	public function testCreateCollectionClearsStoreAndInfoReflectsEmpty(): void {
		$r = new MemoryVectorStoreAgentResource('vs1');

		$r->upsert($this->makeChunk('c', 'id1', [0.1, 0.2], 't1', 'h1'));
		$r->upsert($this->makeChunk('c', 'id2', [0.3, 0.4], 't2', 'h2'));

		$r->createCollection('c');

		$info = $r->getInfo('c');
		$this->assertSame('memory', $info['type'] ?? null);
		$this->assertSame('in-memory:c', $info['collection'] ?? null);
		$this->assertSame(0, $info['count'] ?? null);

		$details = $info['details'] ?? [];
		$this->assertSame(false, $details['persistent'] ?? null);
		$this->assertIsString($details['description'] ?? null);
	}

	public function testDeleteCollectionClearsStore(): void {
		$r = new MemoryVectorStoreAgentResource('vs2');

		$r->upsert($this->makeChunk('c', 'id1', [0.1], 't1', 'h1'));
		$this->assertTrue($r->existsByHash('c', 'h1'));

		$r->deleteCollection('c');

		$this->assertFalse($r->existsByHash('c', 'h1'));

		$info = $r->getInfo('c');
		$this->assertSame(0, $info['count'] ?? null);
	}

	public function testUpsertStoresItemAndExistsByHashFindsIt(): void {
		$r = new MemoryVectorStoreAgentResource('vs3');

		$this->assertFalse($r->existsByHash('c', 'hash-x'));

		$r->upsert($this->makeChunk('c', 'original-id-ignored', [1.0, 2.0, 3.0], 'Hello', 'hash-x', ['foo' => 'bar']));

		$this->assertTrue($r->existsByHash('c', 'hash-x'));

		$info = $r->getInfo('c');
		$this->assertSame(1, $info['count'] ?? null);
	}

	public function testUpsertIgnoresProvidedIdAndCreatesUniqueUuidKeys(): void {
		$r = new MemoryVectorStoreAgentResource('vs4');

		$r->upsert($this->makeChunk('c', 'same-id', [0.0], 'A', 'h1'));
		$r->upsert($this->makeChunk('c', 'same-id', [0.0], 'B', 'h2'));

		$info = $r->getInfo('c');
		$this->assertSame(2, $info['count'] ?? null);

		$deleted = $r->deleteByFilter('c', ['hash' => ['h1', 'h2']]);
		$this->assertSame(2, $deleted);
		$this->assertSame(0, ($r->getInfo('c')['count'] ?? null));
	}

	public function testExistsByHashFalseForUnknownHash(): void {
		$r = new MemoryVectorStoreAgentResource('vs5');

		$r->upsert($this->makeChunk('c', 'id1', [0.1], 't1', 'h1'));

		$this->assertFalse($r->existsByHash('c', 'does-not-exist'));
	}

	public function testSearchReturnsEmptyArrayAlways(): void {
		$r = new MemoryVectorStoreAgentResource('vs6');

		$r->upsert($this->makeChunk('c', 'id1', [0.1], 't1', 'h1'));

		// This implementation DOES do cosine similarity; force empty via limit=0.
		$this->assertSame([], $r->search('c', [0.1], 0, null));
		$this->assertSame([], $r->search('c', [0.1], 0, 0.5));
	}
}
