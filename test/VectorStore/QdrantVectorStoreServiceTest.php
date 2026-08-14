<?php declare(strict_types=1);

namespace MissionBay\Test\VectorStore;

use AssistantFoundation\Api\IRetrievalCollectionDefinition;
use AssistantFoundation\Dto\RetrievalSearchRequest;
use MissionBay\Api\IRetrievalCollectionConfigRepository;
use MissionBay\Retrieval\DefaultRetrievalCollectionDefinition;
use MissionBay\VectorStore\AbstractQdrantVectorStoreService;
use PHPUnit\Framework\TestCase;

final class QdrantVectorStoreServiceTest extends TestCase {

	public function testSearchDoesNotCreateMissingCollection(): void {
		$calls = [];
		$service = $this->createService(
			$this->defaultDefinition(),
			[[
				'http' => 404,
				'raw' => '{"status":{"error":"Not found"}}'
			]],
			$calls
		);
		$service->setOptions([
			'base_url' => 'https://qdrant.example',
			'auth_type' => 'bearer',
			'auth_secret' => 'secret',
			'create_payload_indexes' => true
		]);

		$request = new RetrievalSearchRequest(
			collectionKey: 'default',
			query: 'example',
			mode: RetrievalSearchRequest::MODE_SEMANTIC,
			denseVector: array_fill(0, 1536, 0.1),
			limit: 3
		);

		try {
			$service->search($request);
			$this->fail('Expected missing collection to fail.');
		}
		catch(\RuntimeException $e) {
			$this->assertStringContainsString('search failed HTTP 404', $e->getMessage());
		}

		$this->assertCount(1, $calls);
		$this->assertSame('POST', $calls[0]['method']);
		$this->assertSame(
			'https://qdrant.example/collections/content_v2/points/query',
			$calls[0]['url']
		);
		$this->assertSame('dense_text_v1', $calls[0]['body']['using']);
	}

	public function testHybridSearchUsesNamedDenseSparsePrefetchAndRrf(): void {
		$calls = [];
		$service = $this->createService(
			$this->defaultDefinition(),
			[[
				'http' => 200,
				'raw' => '{"result":{"points":[]}}'
			]],
			$calls
		);
		$service->setOptions([
			'base_url' => 'https://qdrant.example',
			'auth_type' => 'bearer',
			'auth_secret' => 'secret'
		]);

		$service->search(new RetrievalSearchRequest(
			collectionKey: 'default',
			query: 'contract cancellation',
			mode: RetrievalSearchRequest::MODE_HYBRID,
			denseVector: array_fill(0, 1536, 0.2),
			limit: 5,
			candidateLimit: 20
		));

		$body = $calls[0]['body'];
		$this->assertCount(2, $body['prefetch']);
		$this->assertSame('dense_text_v1', $body['prefetch'][0]['using']);
		$this->assertSame('bm25_text_v1', $body['prefetch'][1]['using']);
		$this->assertSame('qdrant/bm25', $body['prefetch'][1]['query']['model']);
		$this->assertSame(['fusion' => 'rrf'], $body['query']);
	}

	public function testPhoneticPhraseConstraintUsesOrderedPhraseFilter(): void {
		$calls = [];
		$definition = $this->createMock(IRetrievalCollectionDefinition::class);
		$definition->method('getBackendCollectionName')->with('docs')->willReturn('retrieval_docs_v1');
		$definition->method('getIndexSchema')->with('docs')->willReturn([
			'phonetic' => [
				'name' => 'phonetic_bm25_v1',
				'model' => 'qdrant/bm25',
				'source' => 'phonetic'
			],
			'phonetic_phrase' => [
				'field' => 'phonetic_phrase_stream',
				'source' => 'phonetic_phrase'
			]
		]);

		$service = $this->createService(
			$definition,
			[[
				'http' => 200,
				'raw' => '{"result":{"points":[]}}'
			]],
			$calls
		);
		$service->setOptions([
			'base_url' => 'https://qdrant.example',
			'auth_type' => 'none'
		]);

		$service->search(new RetrievalSearchRequest(
			collectionKey: 'docs',
			query: 'phonetic query',
			mode: RetrievalSearchRequest::MODE_PHONETIC,
			phoneticPhrases: ['pha phb phc'],
			phoneticText: 'pha phb phc',
			limit: 3
		));

		$body = $calls[0]['body'];
		$this->assertSame('phonetic_bm25_v1', $body['using']);
		$this->assertSame(
			[
				'key' => 'phonetic_phrase_stream',
				'match' => ['phrase' => 'pha phb phc']
			],
			$body['filter']['must'][0]
		);
	}

	public function testInspectPointsUsesLogicalCollectionAndReturnsVectorSummaries(): void {
		$calls = [];
		$definition = $this->createMock(IRetrievalCollectionDefinition::class);
		$definition->method('getBackendCollectionName')->with('docs')->willReturn('retrieval_docs_v1');

		$service = $this->createService(
			$definition,
			[[
				'http' => 200,
				'raw' => json_encode([
					'result' => [
						'points' => [[
							'id' => 'point-1',
							'payload' => ['category' => 'article', 'text' => 'Example'],
							'vector' => [
								'dense_text_v1' => [0.1, 0.2, 0.3],
								'bm25_text_v1' => [
									'indices' => [10, 20],
									'values' => [1.0, 2.0]
								]
							]
						]],
						'next_page_offset' => 'point-2'
					]
				], JSON_UNESCAPED_SLASHES)
			]],
			$calls
		);
		$service->setOptions([
			'base_url' => 'https://qdrant.example',
			'auth_type' => 'none'
		]);

		$result = $service->inspectPoints(
			'docs',
			3,
			[
				'must' => [[
					'field' => 'category',
					'operator' => 'eq',
					'value' => 'article'
				]]
			],
			null,
			true
		);

		$this->assertSame('POST', $calls[0]['method']);
		$this->assertSame(
			'https://qdrant.example/collections/retrieval_docs_v1/points/scroll',
			$calls[0]['url']
		);
		$this->assertTrue($calls[0]['body']['with_vector']);
		$this->assertSame('article', $calls[0]['body']['filter']['must'][0]['match']['value']);
		$this->assertSame('retrieval_docs_v1', $result['collection']);
		$this->assertSame('point-2', $result['next_offset']);
		$this->assertSame(3, $result['points'][0]['vectors']['dense_text_v1']['size']);
		$this->assertSame(2, $result['points'][0]['vectors']['bm25_text_v1']['non_zero']);
	}

	public function testSparseInferenceOptionsAndDatetimeRangeAreForwarded(): void {
		$calls = [];
		$definition = $this->createMock(IRetrievalCollectionDefinition::class);
		$definition->method('getBackendCollectionName')->with('test')->willReturn('test_collection');
		$definition->method('getIndexSchema')->with('test')->willReturn([
			'lexical' => [
				'name' => 'bm25_text_v1',
				'model' => 'qdrant/bm25',
				'source' => 'text',
				'modifier' => 'idf',
				'options' => [
					'stemmer' => ['type' => 'none'],
					'stopwords' => new \stdClass(),
					'tokenizer' => 'multilingual',
					'ascii_folding' => true
				]
			]
		]);

		$service = $this->createService(
			$definition,
			[[
				'http' => 200,
				'raw' => '{"result":{"points":[]}}'
			]],
			$calls
		);
		$service->setOptions([
			'base_url' => 'https://qdrant.example',
			'auth_type' => 'none'
		]);

		$service->search(new RetrievalSearchRequest(
			collectionKey: 'test',
			query: 'Mieville',
			mode: RetrievalSearchRequest::MODE_LEXICAL,
			filterSpec: [
				'must' => [[
					'field' => 'created',
					'operator' => 'range',
					'value' => ['gte' => '2026-01-01 00:00:00']
				]]
			],
			limit: 5
		));

		$body = $calls[0]['body'];
		$this->assertSame('qdrant/bm25', $body['query']['model']);
		$this->assertSame('multilingual', $body['query']['options']['tokenizer']);
		$this->assertSame(['type' => 'none'], $body['query']['options']['stemmer']);
		$this->assertInstanceOf(\stdClass::class, $body['query']['options']['stopwords']);
		$this->assertSame(
			'2026-01-01 00:00:00',
			$body['filter']['must'][0]['range']['gte']
		);
	}

	public function testContextUsesExactPointIdReappliesMandatoryFilterAndSortsNeighbors(): void {
		$calls = [];
		$definition = $this->createMock(IRetrievalCollectionDefinition::class);
		$definition->method('getBackendCollectionName')->with('docs')->willReturn('retrieval_docs_v1');
		$definition->method('getContextSchema')->with('docs')->willReturn([
			'group_field' => 'document_key',
			'position_field' => 'sequence'
		]);
		$definition->method('projectPayload')->willReturnCallback(
			static fn(string $collectionKey, array $payload): array => [
				'text' => $payload['text'] ?? '',
				'sequence' => $payload['sequence'] ?? null
			]
		);

		$pointId = '392482f4-d9c1-5ad5-aff3-57d72a6ac43e';
		$service = $this->createService(
			$definition,
			[
				[
					'http' => 200,
					'raw' => json_encode([
						'result' => [
							'points' => [[
								'id' => $pointId,
								'payload' => [
									'document_key' => 'document-a',
									'sequence' => 2,
									'text' => 'center'
								]
							]]
						]
					], JSON_UNESCAPED_SLASHES)
				],
				[
					'http' => 200,
					'raw' => json_encode([
						'result' => [
							'points' => [
								[
									'id' => '4f98f0de-9054-5d58-8b15-a1c836466e72',
									'payload' => ['document_key' => 'document-a', 'sequence' => 3, 'text' => 'after']
								],
								[
									'id' => $pointId,
									'payload' => ['document_key' => 'document-a', 'sequence' => 2, 'text' => 'center']
								],
								[
									'id' => 'd486bb4a-8074-5e5a-9bc8-bbe9a05e2090',
									'payload' => ['document_key' => 'document-a', 'sequence' => 1, 'text' => 'before']
								]
							]
						]
					], JSON_UNESCAPED_SLASHES)
				]
			],
			$calls
		);
		$service->setOptions([
			'base_url' => 'https://qdrant.example',
			'auth_type' => 'none'
		]);

		$result = $service->context(
			'docs',
			$pointId,
			1,
			1,
			[
				'must' => [[
					'field' => 'visibility_group_ids',
					'operator' => 'in',
					'value' => [4]
				]]
			]
		);

		$this->assertCount(2, $calls);
		$this->assertSame($pointId, $calls[0]['body']['filter']['must'][1]['has_id'][0]);
		$this->assertSame([4], $calls[0]['body']['filter']['must'][0]['match']['any']);
		$this->assertSame('document-a', $calls[1]['body']['filter']['must'][1]['match']['value']);
		$this->assertSame(['gte' => 1, 'lte' => 3], $calls[1]['body']['filter']['must'][2]['range']);
		$this->assertSame([1, 2, 3], array_map(
			static fn($hit): int => (int)$hit->payload['sequence'],
			$result->getHits()
		));
	}

	public function testContextRejectsInvalidBackendPointReferenceBeforeHttpCall(): void {
		$calls = [];
		$definition = $this->createMock(IRetrievalCollectionDefinition::class);
		$definition->method('getBackendCollectionName')->with('docs')->willReturn('retrieval_docs_v1');

		$service = $this->createService($definition, [], $calls);
		$service->setOptions([
			'base_url' => 'https://qdrant.example',
			'auth_type' => 'none'
		]);

		try {
			$service->context('docs', 'derived-document-reference', 1, 1);
			$this->fail('Expected invalid Qdrant point id to fail.');
		}
		catch(\InvalidArgumentException $e) {
			$this->assertStringContainsString('exact retrieval_ref returned by retrieval_search', $e->getMessage());
		}

		$this->assertCount(0, $calls);
	}

	/**
	 * @param array<int,array<string,mixed>> $responses
	 * @param array<int,array<string,mixed>> $calls
	 */
	private function createService(
		IRetrievalCollectionDefinition $definition,
		array $responses,
		array &$calls
	): AbstractQdrantVectorStoreService {
		$calls = [];

		return new class($definition, $responses, $calls) extends AbstractQdrantVectorStoreService {

			private array $responses;
			private array $calls;

			public function __construct(
				IRetrievalCollectionDefinition $definition,
				array $responses,
				array &$calls
			) {
				parent::__construct($definition);
				$this->responses = $responses;
				$this->calls = &$calls;
			}

			public static function getName(): string {
				return 'testqdrantvectorstoreservice';
			}

			protected function buildUrl(string $path): string {
				return rtrim($this->getBaseUrl(), '/') . '/' . ltrim($path, '/');
			}

			protected function buildHeaders(): array {
				return ['Content-Type: application/json'];
			}

			protected function curlJson(string $method, string $url, ?array $body): array {
				$this->calls[] = [
					'method' => $method,
					'url' => $url,
					'body' => $body
				];

				return array_shift($this->responses) ?? [
					'http' => 500,
					'raw' => '',
					'error' => 'No test response configured.'
				];
			}
		};
	}
	private function defaultDefinition(): DefaultRetrievalCollectionDefinition {
		$repository = new class implements IRetrievalCollectionConfigRepository {
			public function getCollections(): array { return ['default' => ['backend_collection' => 'content_v2']]; }
			public function hasCollection(string $collectionKey): bool { return $collectionKey === 'default'; }
			public function getBackendCollectionName(string $collectionKey): string { return 'content_v2'; }
			public function saveCollection(string $collectionKey, string $backendCollection): void {}
			public function removeCollection(string $collectionKey): void {}
		};

		return new DefaultRetrievalCollectionDefinition($repository);
	}

}
