<?php declare(strict_types=1);

namespace MissionBay\Test\VectorStore;

use AssistantFoundation\Api\IRetrievalCollectionDefinition;
use AssistantFoundation\Dto\RetrievalSearchRequest;
use MissionBay\Api\IRetrievalCollectionConfigRepository;
use MissionBay\Retrieval\DefaultRetrievalCollectionDefinition;
use MissionBay\VectorStore\AbstractQdrantVectorStoreService;
use PHPUnit\Framework\TestCase;

final class QdrantVectorStoreServiceTestDouble2 implements IRetrievalCollectionConfigRepository  {
			public function getCollections(): array { return ['default' => ['backend_collection' => 'content_v2']]; }
			public function hasCollection(string $collectionKey): bool { return $collectionKey === 'default'; }
			public function getBackendCollectionName(string $collectionKey): string { return 'content_v2'; }
			public function saveCollection(string $collectionKey, string $backendCollection): void {}
			public function removeCollection(string $collectionKey): void {}
		}
