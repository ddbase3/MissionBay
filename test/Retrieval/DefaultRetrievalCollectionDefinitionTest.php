<?php declare(strict_types=1);

namespace MissionBay\Test\Retrieval;

use AssistantFoundation\Dto\RetrievalIndexItem;
use MissionBay\Api\IRetrievalCollectionConfigRepository;
use MissionBay\Retrieval\DefaultRetrievalCollectionDefinition;
use PHPUnit\Framework\TestCase;

final class DefaultRetrievalCollectionDefinitionTest extends TestCase {

	public function testMapsLogicalCollectionKeysToConfiguredBackendNames(): void {
		$definition = $this->definition();
		$this->assertSame(['public', 'private'], $definition->getCollectionKeys());
		$this->assertSame('collection_public_v2', $definition->getBackendCollectionName('public'));
	}

	public function testIndexAndPayloadSchemasPreserveLexicalAndPhraseFields(): void {
		$definition = $this->definition();
		$index = $definition->getIndexSchema('public');
		$this->assertSame('Cosine', $index['dense']['distance']);
		$this->assertSame('qdrant/bm25', $index['lexical']['model']);
		$this->assertSame('text', $index['phrase']['field']);
		$schema = $definition->getPayloadSchema('public');
		$this->assertTrue($schema['text']['params']['phrase_matching']);
		$this->assertTrue($schema['content_uuid']['index']);
		$this->assertFalse($schema['collection_key']['index']);
	}

	public function testAgentSchemaRestrictsFilterFieldsAndProjectedContent(): void {
		$definition = $this->definition();
		$this->assertSame(['content_uuid'], array_keys($definition->getAgentFilterSchema('public')));
		$this->assertSame(['text', 'chunk_index', 'num_chunks'], $definition->getAgentContextFields('public'));
		$this->assertSame(['group_field' => 'content_uuid', 'position_field' => 'chunk_index'], $definition->getContextSchema('public'));
		$this->assertSame([], $definition->getPhoneticEncoderNames('public', ['lang' => 'de']));
		$this->assertSame(['text' => 'x', 'chunk_index' => 1], $definition->projectPayload('public', [
			'secret' => 'do not expose', 'text' => 'x', 'hash' => 'internal', 'chunk_index' => 1, 'content_uuid' => 'private'
		]));
	}

	public function testValidIndexItemBuildsTrimmedPayloadAndChunkToken(): void {
		$definition = $this->definition();
		$payload = $definition->buildPayload(new RetrievalIndexItem('public', 2, '  Test text  ', '  deadbeef  ', [
			'content_uuid' => '  aa-11  ', 'num_chunks' => 3, 'private_metadata' => 'not-indexed'
		]));
		$this->assertSame('Test text', $payload['text']);
		$this->assertSame('deadbeef', $payload['hash']);
		$this->assertSame('deadbeef-2', $payload['chunktoken']);
		$this->assertSame('aa-11', $payload['content_uuid']);
		$this->assertSame(3, $payload['num_chunks']);
		$this->assertArrayNotHasKey('private_metadata', $payload);
	}

	public function testFirstChunkOmitsSuffixAndInvalidChunkCount(): void {
		$definition = $this->definition();
		foreach ([0, -1, '3', null] as $count) {
			$payload = $definition->buildPayload(new RetrievalIndexItem('public', 0, 'text', ' abc ', [
				'content_uuid' => 'id', 'num_chunks' => $count
			]));
			$this->assertSame('abc', $payload['chunktoken']);
			$this->assertArrayNotHasKey('num_chunks', $payload);
		}
	}

	public function testUnknownCollectionIsDeniedForReadingAndIndexing(): void {
		$definition = $this->definition();
		foreach (['getIndexSchema', 'getPayloadSchema', 'getAgentFilterSchema', 'getContextSchema', 'getAgentContextFields'] as $method) {
			try {
				$definition->$method('unknown');
				$this->fail($method . ' accepted unknown collection.');
			} catch (\InvalidArgumentException $e) {
				$this->assertStringContainsString('unknown', $e->getMessage());
			}
		}
		$this->expectException(\InvalidArgumentException::class);
		$definition->validate(new RetrievalIndexItem('unknown', 0, 'text', 'hash', ['content_uuid' => 'id']));
	}

	public function testMissingOrInvalidRequiredIndexFieldsAreRejected(): void {
		$definition = $this->definition();
		$items = [
			new RetrievalIndexItem('public', -1, 'text', 'hash', ['content_uuid' => 'id']),
			new RetrievalIndexItem('public', 0, " \t ", 'hash', ['content_uuid' => 'id']),
			new RetrievalIndexItem('public', 0, 'text', ' ', ['content_uuid' => 'id']),
			new RetrievalIndexItem('public', 0, 'text', 'hash'),
			new RetrievalIndexItem('public', 0, 'text', 'hash', ['content_uuid' => '  ']),
			new RetrievalIndexItem('public', 0, 'text', 'hash', ['content_uuid' => 14])
		];
		foreach ($items as $item) {
			try {
				$definition->validate($item);
				$this->fail('Invalid retrieval item passed validation.');
			} catch (\RuntimeException $e) {
				$this->assertNotEmpty($e->getMessage());
			}
		}
	}

	private function definition(): DefaultRetrievalCollectionDefinition {
		$repository = $this->createStub(IRetrievalCollectionConfigRepository::class);
		$repository->method('getCollections')->willReturn([
			'public' => ['backend_collection' => 'collection_public_v2'],
			'private' => ['backend_collection' => 'collection_private_v1']
		]);
		$repository->method('hasCollection')->willReturnCallback(static fn(string $key): bool => in_array($key, ['public', 'private'], true));
		$repository->method('getBackendCollectionName')->willReturnCallback(static fn(string $key): string => 'collection_' . $key . '_v2');
		return new DefaultRetrievalCollectionDefinition($repository);
	}
}
