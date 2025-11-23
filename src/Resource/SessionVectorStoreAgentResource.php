<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentVectorStore;

class SessionVectorStoreAgentResource extends AbstractAgentResource implements IAgentVectorStore {

	protected string $sessionKey = 'missionbay_vectorstore';

	public static function getName(): string {
		return 'sessionvectorstoreagentresource';
	}

	public function getDescription(): string {
		return 'Stores vectors temporarily inside the PHP session for testing purposes.';
	}

	/**
	 * Ensure session storage exists.
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
	 * Upsert a vector with flatten payload structure.
	 */
	public function upsert(string $id, array $vector, string $text, array $metadata = []): void {
		$this->ensureSession();

		$_SESSION[$this->sessionKey][$id] = [
			'vector'  => $vector,
			'payload' => array_merge(
				[
					'text' => $text
				],
				$metadata
			)
		];
	}

	/**
	 * Fast duplicate detection by comparing hash.
	 */
	public function existsByHash(string $hash): bool {
		$this->ensureSession();

		foreach ($_SESSION[$this->sessionKey] as $item) {
			if (($item['payload']['hash'] ?? null) === $hash) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Cosine-similarity search over all vectors stored in session.
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
				'id'      => $id,
				'score'   => $score,
				'payload' => $item['payload']
			];
		}

		usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
		return array_slice($results, 0, $limit);
	}

	/**
	 * Pure cosine similarity without external dependencies.
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
