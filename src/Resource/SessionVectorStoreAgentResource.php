<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentVectorStore;

/**
 * SessionVectorStoreAgentResource
 *
 * Vector store implementation that persists embeddings
 * inside the current PHP session. Useful for testing flows
 * without a real vector database.
 *
 * WARNING: Not persistent beyond the session lifetime.
 */
class SessionVectorStoreAgentResource extends AbstractAgentResource implements IAgentVectorStore {

	protected string $sessionKey = 'missionbay_vectorstore';

	public static function getName(): string {
		return 'sessionvectorstoreagentresource';
	}

	public function getDescription(): string {
		return 'Stores vectors temporarily inside the PHP session for testing purposes.';
	}

	/**
	 * Ensures the session array exists.
	 */
	protected function ensureSession(): void {
		if (session_status() !== PHP_SESSION_ACTIVE) {
			@session_start();
		}
		if (!isset($_SESSION[$this->sessionKey])) {
			$_SESSION[$this->sessionKey] = [];
		}
	}

	/**
	 * Upserts vector + metadata into session storage.
	 */
	public function upsert(string $id, array $vector, array $metadata = []): void {
		$this->ensureSession();

		$_SESSION[$this->sessionKey][$id] = [
			'vector' => $vector,
			'meta' => $metadata
		];
	}

	/**
	 * Checks if a content hash already exists
	 * (duplicate detection).
	 */
	public function existsByHash(string $hash): bool {
		$this->ensureSession();

		foreach ($_SESSION[$this->sessionKey] as $item) {
			if (($item['meta']['hash'] ?? null) === $hash) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Very simple similarity search for testing.
	 * Computes cosine similarity on the fly.
	 */
	public function search(array $vector, int $limit = 3, ?float $minScore = null): array {
		$this->ensureSession();

		$results = [];

		foreach ($_SESSION[$this->sessionKey] as $id => $item) {
			$score = $this->cosineSimilarity($vector, $item['vector']);
			if ($minScore !== null && $score < $minScore) {
				continue;
			}
			$results[] = [
				'id' => $id,
				'score' => $score,
				'meta' => $item['meta']
			];
		}

		usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

		return array_slice($results, 0, $limit);
	}

	/**
	 * Basic cosine similarity for testing.
	 * Avoids external dependencies.
	 */
	protected function cosineSimilarity(array $a, array $b): float {
		if (empty($a) || empty($b) || count($a) !== count($b)) {
			return 0.0;
		}

		$dot = 0.0;
		$normA = 0.0;
		$normB = 0.0;

		$len = count($a);
		for ($i = 0; $i < $len; $i++) {
			$dot += $a[$i] * $b[$i];
			$normA += $a[$i] * $a[$i];
			$normB += $b[$i] * $b[$i];
		}

		if ($normA == 0.0 || $normB == 0.0) {
			return 0.0;
		}

		return $dot / (sqrt($normA) * sqrt($normB));
	}
}
