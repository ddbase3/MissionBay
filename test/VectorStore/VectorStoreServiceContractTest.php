<?php declare(strict_types=1);

namespace MissionBay\Test\VectorStore;

use AssistantFoundation\Api\IRetrievalIndex;
use AssistantFoundation\Api\IRetrievalIndexInspector;
use Base3\Api\IBase;
use MissionBay\Api\IVectorStoreService;
use MissionBay\VectorStore\QdrantVectorStoreService;
use PHPUnit\Framework\TestCase;

final class VectorStoreServiceContractTest extends TestCase {

	public function testVectorStoreServiceContractIsNameDiscoverable(): void {
		$this->assertTrue(is_subclass_of(IVectorStoreService::class, IRetrievalIndex::class, true));
		$this->assertTrue(is_subclass_of(IVectorStoreService::class, IRetrievalIndexInspector::class, true));
		$this->assertTrue(is_subclass_of(IVectorStoreService::class, IBase::class, true));
		$this->assertTrue(is_subclass_of(QdrantVectorStoreService::class, IRetrievalIndex::class, true));
		$this->assertTrue(is_subclass_of(QdrantVectorStoreService::class, IRetrievalIndexInspector::class, true));
		$this->assertTrue(is_subclass_of(QdrantVectorStoreService::class, IBase::class, true));
		$this->assertSame('qdrantvectorstoreservice', QdrantVectorStoreService::getName());
	}
}
