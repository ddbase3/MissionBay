<?php declare(strict_types=1);

namespace MissionBay\Test\VectorStore;

use AssistantFoundation\Api\IRetrievalCollectionDefinition;
use AssistantFoundation\Dto\RetrievalSearchRequest;
use MissionBay\Api\IRetrievalCollectionConfigRepository;
use MissionBay\Retrieval\DefaultRetrievalCollectionDefinition;
use MissionBay\VectorStore\AbstractQdrantVectorStoreService;
use PHPUnit\Framework\TestCase;

final class QdrantVectorStoreServiceTestDouble1 extends AbstractQdrantVectorStoreService {

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
		}
