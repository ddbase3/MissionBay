<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAiEmbeddingModel;
use Base3\Database\Api\IDatabase;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Resource\EmbeddingCacheAgentResource;
use PHPUnit\Framework\TestCase;

final class EmbeddingCacheAgentResourceBoundaryTest extends TestCase {

	public function testEmptyInputReturnsEmptyEmbeddingWithoutDatabase(): void {
		$db = $this->createMock(IDatabase::class);
		$db->expects($this->never())->method('connect');
		$cache = $this->cache($db);
		$this->assertSame([], $cache->embed([]));
	}

	public function testEmptyResultHasZeroUsageCounts(): void {
		$cache = $this->cache($this->createStub(IDatabase::class));
		$result = $cache->embedResult([]);
		$this->assertSame([], $result->getEmbeddings());
		$metadata = $result->getMetadata()->toArray();
		$this->assertSame('embedding', $metadata['operation']);
		$this->assertSame('embeddingcacheagentresource', $metadata['extra']['adapter']);
		$this->assertSame([
			'requested_items' => 0,
			'output_vectors' => 0,
			'cache_hits' => 0,
			'cache_misses' => 0
		], $result->getMetadata()->getUsage()->getMetrics());
	}

	public function testMissingEmbeddingDockFailsBeforeDatabaseWrites(): void {
		$db = $this->createMock(IDatabase::class);
		$db->expects($this->never())->method('connect');
		$db->expects($this->never())->method('nonQuery');
		$cache = $this->cache($db);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Missing dock "embedding"');
		$cache->embed(['hello']);
	}

	public function testCacheConfigurationResolvesTableAndScopes(): void {
		$cache = $this->cache($this->createStub(IDatabase::class));
		$cache->setConfig(['table' => ' custom_cache ', 'salt' => ' tenant-42 ', 'model' => ' embedding-v2 ']);
		$options = $cache->getOptions();
		$this->assertSame('custom_cache', $options['cache_table']);
		$this->assertSame('tenant-42', $options['cache_salt_scope']);
		$this->assertSame('embedding-v2', $options['cache_model_scope']);
	}

	public function testUnsetConfigurationKeepsDefaultTable(): void {
		$cache = $this->cache($this->createStub(IDatabase::class));
		$cache->setConfig([]);
		$this->assertSame('base3_embedding_cache', $cache->getOptions()['cache_table']);
	}

	public function testOptionsWithoutEmbeddingIncludeCacheIntrospection(): void {
		$this->assertSame([
			'cache_table' => 'base3_embedding_cache',
			'cache_model_scope' => null,
			'cache_salt_scope' => null
		], $this->cache($this->createStub(IDatabase::class))->getOptions());
	}

	public function testEmbeddingOptionsAreForwardedToDockedModel(): void {
		$model = $this->createMock(IAiEmbeddingModel::class);
		$model->expects($this->once())->method('setOptions')->with(['dimensions' => 256]);
		$cache = $this->cache($this->createStub(IDatabase::class));
		$cache->init(['embedding' => [$model]], $this->createStub(IAgentContext::class));
		$cache->setOptions(['dimensions' => 256]);
	}

	public function testModelOptionsAreExposedAlongsideCacheScope(): void {
		$model = $this->createStub(IAiEmbeddingModel::class);
		$model->method('getOptions')->willReturn(['model' => 'fake-embed', 'dimensions' => 768]);
		$cache = $this->cache($this->createStub(IDatabase::class));
		$cache->init(['embedding' => [$model]], $this->createStub(IAgentContext::class));
		$options = $cache->getOptions();
		$this->assertSame('fake-embed', $options['model']);
		$this->assertSame(768, $options['dimensions']);
		$this->assertSame('base3_embedding_cache', $options['cache_table']);
	}

	public function testEmbeddingDockIsRequiredAndLoggerOptional(): void {
		$docks = $this->cache($this->createStub(IDatabase::class))->getDockDefinitions();
		$this->assertSame('embedding', $docks[0]->name);
		$this->assertTrue($docks[0]->required);
		$this->assertSame('logger', $docks[1]->name);
		$this->assertFalse($docks[1]->required);
	}

	private function cache(IDatabase $db): EmbeddingCacheAgentResource {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$resolver->method('resolveValue')->willReturnCallback(static fn(mixed $value): mixed => $value);
		return new EmbeddingCacheAgentResource($db, $resolver, 'embedding-cache-unit');
	}
}
