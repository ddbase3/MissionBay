<?php declare(strict_types=1);

namespace MissionBay\Test\VectorStore;

use MissionBay\Api\IAgentRagPayloadNormalizer;
use MissionBay\VectorStore\AbstractQdrantVectorStoreService;
use PHPUnit\Framework\TestCase;

final class QdrantVectorStoreServiceTest extends TestCase {

	public function testSearchDoesNotCreateMissingCollection(): void {
		$normalizer = $this->createMock(IAgentRagPayloadNormalizer::class);
		$normalizer->expects($this->once())
			->method('getBackendCollectionName')
			->with('ilias')
			->willReturn('base3ilias_content_v1');

		$calls = [];
		$service = $this->createService(
			$normalizer,
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

		try {
			$service->search('ilias', [0.1, 0.2], 3, 0.4);
			$this->fail('Expected missing collection to fail.');
		}
		catch(\RuntimeException $e) {
			$this->assertStringContainsString('search failed HTTP 404', $e->getMessage());
		}

		$this->assertCount(1, $calls);
		$this->assertSame('POST', $calls[0]['method']);
		$this->assertSame(
			'https://qdrant.example/collections/base3ilias_content_v1/points/search',
			$calls[0]['url']
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $responses
	 * @param array<int,array<string,mixed>> $calls
	 */
	private function createService(
		IAgentRagPayloadNormalizer $normalizer,
		array $responses,
		array &$calls
	): AbstractQdrantVectorStoreService {
		$calls = [];

		return new class($normalizer, $responses, $calls) extends AbstractQdrantVectorStoreService {

			private array $responses;
			private array $calls;

			public function __construct(
				IAgentRagPayloadNormalizer $normalizer,
				array $responses,
				array &$calls
			) {
				parent::__construct($normalizer);
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
}
