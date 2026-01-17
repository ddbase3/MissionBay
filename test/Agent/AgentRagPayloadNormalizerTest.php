<?php declare(strict_types=1);

namespace MissionBay\Test\Agent;

use MissionBay\Agent\AgentRagPayloadNormalizer;
use MissionBay\Dto\AgentEmbeddingChunk;
use PHPUnit\Framework\TestCase;

final class AgentRagPayloadNormalizerTest extends TestCase {

	public function testDefaultCollectionsExposeExpectedDefaults(): void {
		$n = new AgentRagPayloadNormalizer();

		$this->assertSame(['default'], $n->getCollectionKeys());
		$this->assertSame('content_v1', $n->getBackendCollectionName('default'));
		$this->assertSame(1536, $n->getVectorSize('default'));
		$this->assertSame('Cosine', $n->getDistance('default'));

		$schema = $n->getSchema('default');
		$this->assertIsArray($schema);
		$this->assertArrayHasKey('text', $schema);
		$this->assertArrayHasKey('hash', $schema);
		$this->assertArrayHasKey('collection_key', $schema);
		$this->assertArrayHasKey('content_uuid', $schema);
		$this->assertArrayHasKey('chunktoken', $schema);
		$this->assertArrayHasKey('chunk_index', $schema);
		$this->assertArrayHasKey('meta', $schema);
	}

	public function testValidateThrowsWhenCollectionKeyMissing(): void {
		$n = new AgentRagPayloadNormalizer();

		$chunk = $this->makeChunk([
			'collectionKey' => '',
		]);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('AgentEmbeddingChunk.collectionKey is required.');
		$n->validate($chunk);
	}

	public function testValidateThrowsWhenUnknownCollectionKey(): void {
		$n = new AgentRagPayloadNormalizer();

		$chunk = $this->makeChunk([
			'collectionKey' => 'nope',
		]);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("Unknown collectionKey 'nope'.");
		$n->validate($chunk);
	}

	public function testValidateThrowsWhenChunkIndexInvalid(): void {
		$n = new AgentRagPayloadNormalizer();

		$chunk = $this->makeChunk([
			'chunkIndex' => -1,
		]);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('AgentEmbeddingChunk.chunkIndex must be >= 0.');
		$n->validate($chunk);
	}

	public function testValidateThrowsWhenTextEmpty(): void {
		$n = new AgentRagPayloadNormalizer();

		$chunk = $this->makeChunk([
			'text' => '   ',
		]);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('AgentEmbeddingChunk.text must be non-empty.');
		$n->validate($chunk);
	}

	public function testValidateThrowsWhenHashEmpty(): void {
		$n = new AgentRagPayloadNormalizer();

		$chunk = $this->makeChunk([
			'hash' => '',
		]);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('AgentEmbeddingChunk.hash must be non-empty.');
		$n->validate($chunk);
	}

	public function testMetadataCannotBeNonArrayBecauseDtoIsTyped(): void {
		$chunk = $this->makeChunk();

		$this->expectException(\TypeError::class);

		// This already fails due to typed property: public array $metadata
		$this->setProp($chunk, 'metadata', null);
	}

	public function testValidateThrowsWhenRequiredMetaMissing(): void {
		$n = new AgentRagPayloadNormalizer();

		$chunk = $this->makeChunk([
			'metadata' => [], // missing content_uuid
		]);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage("Missing required metadata field 'content_uuid'.");
		$n->validate($chunk);
	}

	public function testBuildPayloadBuildsExpectedFieldsAndMetaFiltering(): void {
		$n = new AgentRagPayloadNormalizer();

		$chunk = $this->makeChunk([
			'chunkIndex' => 0,
			'hash' => 'h123',
			'text' => ' hello ',
			'metadata' => [
				'content_uuid' => 'c-1',
				'title' => 'T',
				'lang' => 'de',

				// workflow keys must be excluded
				'job_id' => 'j',
				'attempts' => 2,
				'error_message' => 'x',

				// internal keys must be excluded
				'action' => 'upsert',
				'collectionKey' => 'default',
				'collection_key' => 'default',
			],
		]);

		$payload = $n->buildPayload($chunk);

		$this->assertSame('hello', $payload['text']);
		$this->assertSame('h123', $payload['hash']);
		$this->assertSame('default', $payload['collection_key']);
		$this->assertSame('h123', $payload['chunktoken']);
		$this->assertSame(0, $payload['chunk_index']);
		$this->assertSame('c-1', $payload['content_uuid']);

		$this->assertArrayHasKey('meta', $payload);
		$this->assertSame('T', $payload['meta']['title'] ?? null);
		$this->assertSame('de', $payload['meta']['lang'] ?? null);

		$this->assertArrayNotHasKey('job_id', $payload['meta']);
		$this->assertArrayNotHasKey('attempts', $payload['meta']);
		$this->assertArrayNotHasKey('error_message', $payload['meta']);
		$this->assertArrayNotHasKey('action', $payload['meta']);
		$this->assertArrayNotHasKey('collectionKey', $payload['meta']);
		$this->assertArrayNotHasKey('collection_key', $payload['meta']);

		// known key must not be duplicated into meta
		$this->assertArrayNotHasKey('content_uuid', $payload['meta']);
	}

	public function testBuildPayloadChunkTokenAppendsIndexWhenIndexGreaterZero(): void {
		$n = new AgentRagPayloadNormalizer();

		$chunk = $this->makeChunk([
			'chunkIndex' => 3,
			'hash' => 'h999',
			'metadata' => ['content_uuid' => 'c-9'],
		]);

		$payload = $n->buildPayload($chunk);

		$this->assertSame('h999-3', $payload['chunktoken']);
		$this->assertSame(3, $payload['chunk_index']);
	}

	// ------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------

	/**
	 * @param array<string,mixed> $overrides
	 */
	private function makeChunk(array $overrides = []): AgentEmbeddingChunk {
		/** @var AgentEmbeddingChunk $chunk */
		$chunk = (new \ReflectionClass(AgentEmbeddingChunk::class))->newInstanceWithoutConstructor();

		$defaults = [
			'collectionKey' => 'default',
			'chunkIndex' => 0,
			'text' => 'text',
			'hash' => 'hash',
			'metadata' => ['content_uuid' => 'content-uuid'],
		];

		$data = array_merge($defaults, $overrides);

		foreach ($data as $k => $v) {
			$this->setProp($chunk, $k, $v);
		}

		return $chunk;
	}

	private function setProp(object $obj, string $prop, mixed $value): void {
		// public DTO? set directly
		if (property_exists($obj, $prop)) {
			$obj->{$prop} = $value;
			return;
		}

		// fallback reflection for private/protected or different visibility
		$ref = new \ReflectionObject($obj);
		if (!$ref->hasProperty($prop)) {
			$this->fail('Missing property ' . get_class($obj) . '::$' . $prop . ' (DTO differs; please paste AgentEmbeddingChunk).');
		}

		$p = $ref->getProperty($prop);
		$p->setAccessible(true);
		$p->setValue($obj, $value);
	}
}
