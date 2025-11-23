<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentVectorStore;

/**
 * MemoryVectorStoreAgentResource
 *
 * Simple in-memory vector store for development and testing.
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

	public function upsert(string $id, array $vector, string $text, string $hash, array $metadata = []): void {
		$this->store[$id] = [
			'vector'  => $vector,
			'payload' => array_merge([
				'text' => $text,
				'hash' => $hash
			], $metadata)
		];
	}

	public function existsByHash(string $hash): bool {
		foreach ($this->store as $item) {
			if (($item['payload']['hash'] ?? null) === $hash) {
				return true;
			}
		}
		return false;
	}

	public function search(array $vector, int $limit = 3, ?float $minScore = null): array {
		// no similarity search here; dev-only
		return [];
	}

	public function createCollection(): void {
		// in-memory; nothing to create
		$this->store = [];
	}

	public function deleteCollection(): void {
		// just clear everything
		$this->store = [];
	}
}
