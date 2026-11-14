<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace Base3\MissionBay\Test\VectorStore;

use Base3\AssistantFoundation\Api\IRetrievalIndex;
use Base3\AssistantFoundation\Api\IRetrievalIndexInspector;
use Base3\CoreFoundation\Api\IBase;
use Base3\MissionBay\Api\IVectorStoreService;
use Base3\MissionBay\VectorStore\QdrantVectorStoreService;
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
