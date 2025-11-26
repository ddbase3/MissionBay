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
		$uuid = $this->generateUuid();

		$this->store[$uuid] = [
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
		$this->store = [];
	}

	public function deleteCollection(): void {
		$this->store = [];
	}

	public function getInfo(): array {
		$count = count($this->store);
		$ids = array_keys($this->store);

		return [
			'type'       => 'memory',
			'collection' => 'in-memory',
			'count'      => $count,
			'ids'        => $ids,
			'details'    => [
				'persistent'  => false,
				'description' => 'Simple volatile memory-based vector store.'
			]
		];
	}

	/**
	 * Generates UUID v4.
	 */
	protected function generateUuid(): string {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
	}
}
