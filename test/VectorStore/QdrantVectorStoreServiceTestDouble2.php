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

use Base3\AssistantFoundation\Api\IRetrievalCollectionDefinition;
use Base3\AssistantFoundation\Dto\RetrievalSearchRequest;
use Base3\MissionBay\Api\IRetrievalCollectionConfigRepository;
use Base3\MissionBay\Retrieval\DefaultRetrievalCollectionDefinition;
use Base3\MissionBay\VectorStore\AbstractQdrantVectorStoreService;
use PHPUnit\Framework\TestCase;

final class QdrantVectorStoreServiceTestDouble2 implements IRetrievalCollectionConfigRepository  {
			public function getCollections(): array { return ['default' => ['backend_collection' => 'content_v2']]; }
			public function hasCollection(string $collectionKey): bool { return $collectionKey === 'default'; }
			public function getBackendCollectionName(string $collectionKey): string { return 'content_v2'; }
			public function saveCollection(string $collectionKey, string $backendCollection): void {}
			public function removeCollection(string $collectionKey): void {}
		}
