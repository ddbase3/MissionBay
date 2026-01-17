<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\QdrantVectorStoreAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentRagPayloadNormalizer;
use MissionBay\Dto\AgentEmbeddingChunk;

/**
 * @covers \MissionBay\Resource\QdrantVectorStoreAgentResource
 */
class QdrantVectorStoreAgentResourceTest extends TestCase {

	/**
	 * Create a testable resource by overriding curlJson and recording all calls.
	 *
	 * Important: Do not pass an uninitialized variable into a by-ref parameter.
	 * Always initialize $outCalls = [] before calling this method.
	 *
	 * @param array<int,array<string,mixed>> $httpQueue
	 * @param array<int,array<string,mixed>> $outCalls
	 */
	private function makeResource(array $httpQueue, array &$outCalls, ?IAgentConfigValueResolver $resolver = null, ?IAgentRagPayloadNormalizer $normalizer = null): QdrantVectorStoreAgentResource {
		$outCalls = [];

		$resolver = $resolver ?? new class implements IAgentConfigValueResolver {
			public function resolveValue(array|string|int|float|bool|null $config): mixed {
				return $config;
			}
		};

		$normalizer = $normalizer ?? $this->createStub(IAgentRagPayloadNormalizer::class);

		return new class($resolver, $normalizer, 't1', $httpQueue, $outCalls) extends QdrantVectorStoreAgentResource {
			/** @var array<int,array<string,mixed>> */
			private array $queue;

			/** @var array<int,array<string,mixed>> */
			private array $callsRef;

			public function __construct(IAgentConfigValueResolver $resolver, IAgentRagPayloadNormalizer $normalizer, ?string $id, array $queue, array &$callsRef) {
				parent::__construct($resolver, $normalizer, $id);
				$this->queue = $queue;
				$this->callsRef = &$callsRef;
			}

			protected function curlJson(string $method, string $url, ?array $body): array {
				$this->callsRef[] = [
					'method' => $method,
					'url' => $url,
					'body' => $body
				];

				if (empty($this->queue)) {
					return ['http' => 500, 'raw' => '', 'error' => 'queue empty'];
				}

				$next = array_shift($this->queue);

				return [
					'http' => $next['http'] ?? 200,
					'raw' => $next['raw'] ?? '',
					'error' => $next['error'] ?? ''
				];
			}
		};
	}

	private function makeResolverStub(array $returnsByInput): IAgentConfigValueResolver {
		return new class($returnsByInput) implements IAgentConfigValueResolver {
			private array $map;

			public function __construct(array $map) {
				$this->map = $map;
			}

			public function resolveValue(array|string|int|float|bool|null $config): mixed {
				$key = is_array($config) ? json_encode($config) : (string)$config;
				if (array_key_exists($key, $this->map)) {
					return $this->map[$key];
				}
				return $config;
			}
		};
	}

	private function uuidV5(string $namespaceUuid, string $name): string {
		$nsHex = str_replace('-', '', strtolower(trim($namespaceUuid)));
		$nsBin = hex2bin($nsHex);
		$this->assertNotFalse($nsBin);

		$hash = sha1($nsBin . $name);

		$timeLow = substr($hash, 0, 8);
		$timeMid = substr($hash, 8, 4);
		$timeHi = substr($hash, 12, 4);
		$clkSeq = substr($hash, 16, 4);
		$node = substr($hash, 20, 12);

		$timeHiVal = (hexdec($timeHi) & 0x0fff) | 0x5000;
		$clkSeqVal = (hexdec($clkSeq) & 0x3fff) | 0x8000;

		return sprintf(
			'%s-%s-%04x-%04x-%s',
			$timeLow,
			$timeMid,
			$timeHiVal,
			$clkSeqVal,
			$node
		);
	}

	public function testGetName(): void {
		$this->assertSame('qdrantvectorstoreagentresource', QdrantVectorStoreAgentResource::getName());
	}

	public function testGetDescription(): void {
		$calls = [];
		$res = $this->makeResource([], $calls);
		$this->assertSame('Provides vector upsert, search, and duplicate detection for Qdrant (multi-collection).', $res->getDescription());
	}

	public function testSetConfigTrimsEndpointAndRequiresEndpointAndApikey(): void {
		$resolver = $this->makeResolverStub([
			'endpoint_spec' => ' https://example.local/qdrant/ ',
			'apikey_spec' => ' key123 ',
			'true' => true
		]);

		$calls = [];
		$res = $this->makeResource([], $calls, $resolver);

		$res->setConfig([
			'endpoint' => 'endpoint_spec',
			'apikey' => 'apikey_spec',
			'create_payload_indexes' => true
		]);

		$calls2 = [];
		$res2 = $this->makeResource([], $calls2, $this->makeResolverStub([
			'endpoint_spec' => '   ',
			'apikey_spec' => 'x'
		]));

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('QdrantVectorStore: endpoint is required.');
		$res2->setConfig(['endpoint' => 'endpoint_spec', 'apikey' => 'apikey_spec']);
	}

	public function testSetConfigRequiresApikey(): void {
		$calls = [];
		$res = $this->makeResource([], $calls, $this->makeResolverStub([
			'endpoint_spec' => 'https://example.local',
			'apikey_spec' => ''
		]));

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('QdrantVectorStore: apikey is required.');
		$res->setConfig(['endpoint' => 'endpoint_spec', 'apikey' => 'apikey_spec']);
	}

	public function testExistsByHashReturnsFalseOnEmptyHashWithoutCallingBackend(): void {
		$resolver = $this->makeResolverStub([
			'endpoint_spec' => 'https://qdrant.local',
			'apikey_spec' => 'secret'
		]);

		$calls = [];
		$res = $this->makeResource([], $calls, $resolver, $this->createStub(IAgentRagPayloadNormalizer::class));
		$res->setConfig(['endpoint' => 'endpoint_spec', 'apikey' => 'apikey_spec', 'create_payload_indexes' => false]);

		$this->assertFalse($res->existsByHash('lm', '   '));
		$this->assertCount(0, $calls);
	}

	public function testExistsByFilterPostsScrollAndParsesPointsPresence(): void {
		$resolver = $this->makeResolverStub([
			'endpoint_spec' => 'https://qdrant.local',
			'apikey_spec' => 'secret'
		]);

		$normalizer = $this->createMock(IAgentRagPayloadNormalizer::class);
		$normalizer->expects($this->exactly(2))->method('getBackendCollectionName')->with('lm')->willReturn('ilias_lm_v1');

		$httpQueue = [
			['http' => 200, 'raw' => '{"result":{"status":"ok"}}'], // ensureCollection GET -> exists
			['http' => 200, 'raw' => '{"result":{"points":[{"id":"x"}]}}'] // scroll -> found
		];

		$calls = [];
		$res = $this->makeResource($httpQueue, $calls, $resolver, $normalizer);
		$res->setConfig(['endpoint' => 'endpoint_spec', 'apikey' => 'apikey_spec', 'create_payload_indexes' => false]);

		$ok = $res->existsByFilter('lm', ['hash' => 'h1']);
		$this->assertTrue($ok);
	}

	public function testDeleteByFilterReturnsDeletedCountIfProvided(): void {
		$resolver = $this->makeResolverStub([
			'endpoint_spec' => 'https://qdrant.local',
			'apikey_spec' => 'secret'
		]);

		$normalizer = $this->createMock(IAgentRagPayloadNormalizer::class);
		$normalizer->expects($this->exactly(2))->method('getBackendCollectionName')->with('lm')->willReturn('ilias_lm_v1');

		$httpQueue = [
			['http' => 200, 'raw' => '{"result":{"status":"ok"}}'], // ensureCollection GET -> exists
			['http' => 200, 'raw' => '{"result":{"deleted":7}}'] // delete
		];

		$calls = [];
		$res = $this->makeResource($httpQueue, $calls, $resolver, $normalizer);
		$res->setConfig(['endpoint' => 'endpoint_spec', 'apikey' => 'apikey_spec', 'create_payload_indexes' => false]);

		$n = $res->deleteByFilter('lm', ['content_uuid' => 'c1']);
		$this->assertSame(7, $n);
	}

	public function testSearchAppliesMinScoreAndReturnsNormalizedHits(): void {
		$resolver = $this->makeResolverStub([
			'endpoint_spec' => 'https://qdrant.local',
			'apikey_spec' => 'secret'
		]);

		$normalizer = $this->createMock(IAgentRagPayloadNormalizer::class);
		$normalizer->expects($this->exactly(2))->method('getBackendCollectionName')->with('lm')->willReturn('ilias_lm_v1');

		$httpQueue = [
			['http' => 200, 'raw' => '{"result":{"status":"ok"}}'], // ensureCollection GET -> exists
			['http' => 200, 'raw' => json_encode([
				'result' => [
					['id' => 'a', 'score' => 0.9, 'payload' => ['x' => 1]],
					['id' => 'b', 'score' => 0.2, 'payload' => ['x' => 2]],
					['id' => 'c', 'payload' => ['x' => 3]]
				]
			])]
		];

		$calls = [];
		$res = $this->makeResource($httpQueue, $calls, $resolver, $normalizer);
		$res->setConfig(['endpoint' => 'endpoint_spec', 'apikey' => 'apikey_spec', 'create_payload_indexes' => false]);

		$out = $res->search('lm', [0.1, 0.2], 3, 0.5, [
			'must' => ['public' => 1],
			'any' => ['tags' => ['t1', 't2']],
			'must_not' => ['archive' => 1]
		]);

		$this->assertSame(
			[
				['id' => 'a', 'score' => 0.9, 'payload' => ['x' => 1]]
			],
			$out
		);
	}

	public function testGetInfoReturnsNormalizedStructure(): void {
		$resolver = $this->makeResolverStub([
			'endpoint_spec' => 'https://qdrant.local',
			'apikey_spec' => 'secret'
		]);

		$normalizer = $this->createMock(IAgentRagPayloadNormalizer::class);
		$normalizer->expects($this->once())->method('getBackendCollectionName')->with('lm')->willReturn('ilias_lm_v1');
		$normalizer->expects($this->once())->method('getVectorSize')->with('lm')->willReturn(1536);
		$normalizer->expects($this->once())->method('getDistance')->with('lm')->willReturn('Cosine');

		$httpQueue = [
			['http' => 200, 'raw' => json_encode(['result' => ['payload_schema' => ['hash' => ['type' => 'keyword']]]])]
		];

		$calls = [];
		$res = $this->makeResource($httpQueue, $calls, $resolver, $normalizer);
		$res->setConfig(['endpoint' => 'endpoint_spec', 'apikey' => 'apikey_spec', 'create_payload_indexes' => false]);

		$info = $res->getInfo('lm');

		$this->assertSame('lm', $info['collection_key']);
		$this->assertSame('ilias_lm_v1', $info['collection']);
		$this->assertSame(1536, $info['vector_size']);
		$this->assertSame('Cosine', $info['distance']);
		$this->assertSame(['hash' => ['type' => 'keyword']], $info['payload_schema']);
		$this->assertIsArray($info['qdrant_raw']);
	}

	public function testUpsertEnsuresCollectionCreatesIndexesAndUpsertsPointWithDeterministicUuidV5(): void {
		$resolver = $this->makeResolverStub([
			'endpoint_spec' => 'https://qdrant.local/',
			'apikey_spec' => 'secret',
			'flag_spec' => true
		]);

		$normalizer = $this->createMock(IAgentRagPayloadNormalizer::class);
		$normalizer->expects($this->once())->method('validate');
		$normalizer->expects($this->atLeast(1))->method('getBackendCollectionName')->with('lm')->willReturn('ilias_lm_v1');
		$normalizer->expects($this->once())->method('buildPayload')->willReturn([
			'hash' => 'h1',
			'content_uuid' => 'c1',
			'type_alias' => 'text'
		]);
		$normalizer->expects($this->once())->method('getVectorSize')->with('lm')->willReturn(3);
		$normalizer->expects($this->once())->method('getDistance')->with('lm')->willReturn('Cosine');
		$normalizer->expects($this->exactly(2))->method('getSchema')->with('lm')->willReturn([
			'hash' => ['type' => 'keyword', 'index' => true],
			'content_uuid' => ['type' => 'uuid', 'index' => true],
			'type_alias' => ['type' => 'keyword', 'index' => false]
		]);

		$httpQueue = [
			['http' => 404, 'raw' => ''], // ensureCollection GET -> missing
			['http' => 200, 'raw' => '{"result":{}}'], // createCollection PUT
			['http' => 200, 'raw' => '{"result":{}}'], // ensureIndex hash
			['http' => 200, 'raw' => '{"result":{}}'], // ensureIndex content_uuid
			['http' => 200, 'raw' => '{"result":{}}'] // upsert PUT points
		];

		$calls = [];
		$res = $this->makeResource($httpQueue, $calls, $resolver, $normalizer);
		$res->setConfig([
			'endpoint' => 'endpoint_spec',
			'apikey' => 'apikey_spec',
			'create_payload_indexes' => 'flag_spec'
		]);

		$chunk = new AgentEmbeddingChunk(
			collectionKey: 'lm',
			chunkIndex: 2,
			text: 'Hello',
			hash: 'h1',
			metadata: ['content_uuid' => 'c1'],
			vector: [0.1, 0.2, 0.3]
		);

		$res->upsert($chunk);

		$expectedId = $this->uuidV5('6ba7b810-9dad-11d1-80b4-00c04fd430c8', 'h1:2');

		$this->assertCount(5, $calls);
		$this->assertSame($expectedId, $calls[4]['body']['points'][0]['id']);
	}
}
