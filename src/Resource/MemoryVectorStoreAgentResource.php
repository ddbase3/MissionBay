<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentVectorStore;

/**
 * MemoryVectorStoreAgentResource
 *
 * Simple in-memory vector DB for testing purposes.
 * Not persistent and not optimized for similarity search.
 */
class MemoryVectorStoreAgentResource extends AbstractAgentResource implements IAgentVectorStore {

	/** @var array<string,array<string,mixed>> */
	protected array $store = [];

	public static function getName(): string {
		return 'memoryvectorstoreagentresource';
	}

	public function getDescription(): string {
		return 'Non-persistent in-memory vector store for development and testing.';
	}

	public function upsert(string $id, array $vector, array $metadata = []): void {
		$this->store[$id] = [
			'vector' => $vector,
			'meta' => $metadata
		];
	}

	public function existsByHash(string $hash): bool {
		foreach ($this->store as $item) {
			if (($item['meta']['hash'] ?? null) === $hash) {
				return true;
			}
		}
		return false;
	}

	public function search(array $vector, int $limit = 3, ?float $minScore = null): array {
		// Dummy: no real vector math, always return empty set.
		return [];
	}
}
