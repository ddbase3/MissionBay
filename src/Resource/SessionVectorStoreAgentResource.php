<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentVectorStore;
use MissionBay\Api\IAgentRagPayloadNormalizer;

/**
 * SessionVectorStoreAgentResource
 *
 * Temporary vector store based on PHP session.
 * Uses same RAG payload normalizer as Qdrant for full compatibility.
 */
class SessionVectorStoreAgentResource extends AbstractAgentResource implements IAgentVectorStore {

	protected string $sessionKey = 'missionbay_vectorstore';

	protected IAgentRagPayloadNormalizer $normalizer;

	public function __construct(
		IAgentRagPayloadNormalizer $normalizer,
		?string $id = null
	) {
		parent::__construct($id);
		$this->normalizer = $normalizer;
	}

	public static function getName(): string {
		return 'sessionvectorstoreagentresource';
	}

	public function getDescription(): string {
		return 'In-memory vector store using PHP sessions. Uses full RAG payload normalization.';
	}

	// ---------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------

	protected function ensureSession(): void {
		if (session_status() !== PHP_SESSION_ACTIVE) {
			@session_start();
		}
		if (!isset($_SESSION[$this->sessionKey])) {
			$_SESSION[$this->sessionKey] = [];
		}
	}

	// ---------------------------------------------------------
	// Upsert (UUID)
	// ---------------------------------------------------------

	public function upsert(string $id, array $vector, string $text, string $hash, array $metadata = []): void {
		$this->ensureSession();

		$uuid = $this->generateUuid();
		$payload = $this->normalizer->normalize($text, $hash, $metadata);

		$_SESSION[$this->sessionKey][$uuid] = [
			'vector'  => $vector,
			'payload' => $payload
		];
	}

	// ---------------------------------------------------------
	// Duplicate Detection
	// ---------------------------------------------------------

	public function existsByHash(string $hash): bool {
		$this->ensureSession();

		foreach ($_SESSION[$this->sessionKey] as $item) {
			if (($item['payload']['hash'] ?? null) === $hash) {
				return true;
			}
		}
		return false;
	}

	// ---------------------------------------------------------
	// Search
	// ---------------------------------------------------------

	public function search(array $vector, int $limit = 3, ?float $minScore = null): array {
		$this->ensureSession();

		$results = [];

		foreach ($_SESSION[$this->sessionKey] as $id => $item) {
			$score = $this->cosineSimilarity($vector, $item['vector']);
			if ($minScore !== null && $score < $minScore) continue;

			$results[] = [
				'id'      => $id,
				'score'   => $score,
				'payload' => $item['payload']
			];
		}

		usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

		return array_slice($results, 0, $limit);
	}

	protected function cosineSimilarity(array $a, array $b): float {
		if (empty($a) || empty($b) || count($a) !== count($b)) {
			return 0.0;
		}

		$dot = 0.0;
		$normA = 0.0;
		$normB = 0.0;

		$len = count($a);
		for ($i = 0; $i < $len; $i++) {
			$dot   += $a[$i] * $b[$i];
			$normA += $a[$i] * $a[$i];
			$normB += $b[$i] * $b[$i];
		}

		if ($normA == 0.0 || $normB == 0.0) {
			return 0.0;
		}

		return $dot / (sqrt($normA) * sqrt($normB));
	}

	// ---------------------------------------------------------
	// Collection Lifecycle
	// ---------------------------------------------------------

	public function createCollection(): void {
		$this->ensureSession();
		$_SESSION[$this->sessionKey] = [];
	}

	public function deleteCollection(): void {
		$this->ensureSession();
		unset($_SESSION[$this->sessionKey]);
	}

	// ---------------------------------------------------------
	// getInfo()
	// ---------------------------------------------------------

	public function getInfo(): array {
		$this->ensureSession();

		$items = $_SESSION[$this->sessionKey] ?? [];
		$count = count($items);
		$ids = array_keys($items);

		return [
			'type'       => 'session',
			'collection' => $this->sessionKey,
			'count'      => $count,
			'ids'        => $ids,
			'details'    => [
				'persistent'  => false,
				'description' => 'Session-based vector store with RAG payload normalization.',
				'normalized_payloads' => true
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
