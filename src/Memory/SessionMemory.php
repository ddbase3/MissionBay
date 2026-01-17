<?php declare(strict_types=1);

namespace MissionBay\Memory;

use MissionBay\Api\IAgentMemory;
use Base3\Session\Api\ISession;

class SessionMemory implements IAgentMemory {

	private int $max = 20;

	public function __construct(private readonly ISession $session) {}

	public static function getName(): string {
		return 'sessionmemory';
	}

	/**
	 * Ensures the mb_memory structure exists in $_SESSION.
	 *
	 * IMPORTANT:
	 * We must not touch $_SESSION at all if the session is not started,
	 * because PHP would implicitly create session arrays otherwise.
	 */
	private function ensure(): bool {
		if (!$this->session->started()) {
			return false;
		}

		if (!isset($_SESSION['mb_memory'])) {
			$_SESSION['mb_memory'] = ['nodes' => [], 'data' => []];
		}
		if (!isset($_SESSION['mb_memory']['nodes'])) {
			$_SESSION['mb_memory']['nodes'] = [];
		}
		if (!isset($_SESSION['mb_memory']['data'])) {
			$_SESSION['mb_memory']['data'] = [];
		}

		return true;
	}

	public function loadNodeHistory(string $nodeId): array {
		if (!$this->ensure()) {
			return [];
		}
		return $_SESSION['mb_memory']['nodes'][$nodeId] ?? [];
	}

	public function appendNodeHistory(string $nodeId, array $message): void {
		if (!$this->ensure()) {
			return;
		}

		$_SESSION['mb_memory']['nodes'][$nodeId][] = $message;

		if (count($_SESSION['mb_memory']['nodes'][$nodeId]) > $this->max) {
			$_SESSION['mb_memory']['nodes'][$nodeId] = array_slice(
				$_SESSION['mb_memory']['nodes'][$nodeId],
				-$this->max
			);
		}
	}

	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
		if (!$this->ensure()) {
			return false;
		}

		if (!isset($_SESSION['mb_memory']['nodes'][$nodeId])) {
			return false;
		}

		foreach ($_SESSION['mb_memory']['nodes'][$nodeId] as &$entry) {
			if (($entry['id'] ?? null) === $messageId) {
				$entry['feedback'] = $feedback;
				return true;
			}
		}

		return false;
	}

	public function resetNodeHistory(string $nodeId): void {
		if (!$this->ensure()) {
			return;
		}

		unset($_SESSION['mb_memory']['nodes'][$nodeId]);
	}

	public function getPriority(): int {
		return 0;
	}
}
