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

namespace MissionBay\Resource;

use MissionBay\Api\IAgentVectorStore;
use MissionBay\Dto\AgentEmbeddingChunk;

/**
 * NoVectorStoreAgentResource
 *
 * A no-operation vector store resource.
 * Does not store, search, or return any data.
 *
 * Implements IAgentVectorStore (multi-collection contract), but intentionally does nothing.
 */
final class NoVectorStoreAgentResource extends AbstractAgentResource implements IAgentVectorStore {

	public static function getName(): string {
		return 'novectorstoreagentresource';
	}

	public function getDescription(): string {
		return 'A no-operation vector store that does not store or retrieve vectors.';
	}

	public function upsert(AgentEmbeddingChunk $chunk): void {
		// Intentionally no-op
	}

	public function existsByHash(string $collectionKey, string $hash): bool {
		return false;
	}

	public function existsByFilter(string $collectionKey, array $filter): bool {
		return false;
	}

	public function deleteByFilter(string $collectionKey, array $filter): int {
		return 0;
	}

	public function search(
		string $collectionKey,
		array $vector,
		int $limit = 3,
		?float $minScore = null,
		?array $filterSpec = null
	): array {
		return [];
	}

	public function createCollection(string $collectionKey): void {
		// Intentionally no-op
	}

	public function deleteCollection(string $collectionKey): void {
		// Intentionally no-op
	}

	public function getInfo(string $collectionKey): array {
		return [
			'type' => 'no-op',
			'collection_key' => $collectionKey,
			'collection' => null,
			'count' => 0,
			'details' => [
				'persistent' => false,
				'description' => 'This vector store does not store or return any data.'
			]
		];
	}
}
