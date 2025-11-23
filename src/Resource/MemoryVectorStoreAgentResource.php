<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentVectorStore;

class MemoryVectorStoreAgentResource extends AbstractAgentResource implements IAgentVectorStore {

		protected array $store = [];

		public static function getName(): string {
				return 'memoryvectorstoreagentresource';
		}

		public function getDescription(): string {
				return 'Non-persistent in-memory vector store for development and testing.';
		}

		/**
		 * Stores an embedding vector in memory with flattened payload fields.
		 */
		public function upsert(string $id, array $vector, string $text, array $metadata = []): void {
				$this->store[$id] = [
						'vector' => $vector,
						'payload' => array_merge(
								[
										'text' => $text
								],
								$metadata
						)
				];
		}

		/**
		 * Duplicate detection by comparing the stored "hash" field.
		 */
		public function existsByHash(string $hash): bool {
				foreach ($this->store as $item) {
						if (($item['payload']['hash'] ?? null) === $hash) {
								return true;
						}
				}
				return false;
		}

		/**
		 * Dummy search – no similarity evaluation in memory version.
		 */
		public function search(array $vector, int $limit = 3, ?float $minScore = null): array {
				return [];
		}
}
