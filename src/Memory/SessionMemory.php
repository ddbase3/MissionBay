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

	private function ensure(): void {
		if (!$this->session->started()) return;
		if (!isset($_SESSION['mb_memory'])) {
			$_SESSION['mb_memory'] = ['nodes' => [], 'data' => []];
		}
		if (!isset($_SESSION['mb_memory']['nodes'])) {
			$_SESSION['mb_memory']['nodes'] = [];
		}
		if (!isset($_SESSION['mb_memory']['data'])) {
			$_SESSION['mb_memory']['data'] = [];
		}
	}

	public function loadNodeHistory(string $nodeId): array {
		$this->ensure();
		return $_SESSION['mb_memory']['nodes'][$nodeId] ?? [];
	}

	public function appendNodeHistory(string $nodeId, array $message): void {
		$this->ensure();
		$_SESSION['mb_memory']['nodes'][$nodeId][] = $message;

		if (count($_SESSION['mb_memory']['nodes'][$nodeId]) > $this->max) {
			$_SESSION['mb_memory']['nodes'][$nodeId] = array_slice(
				$_SESSION['mb_memory']['nodes'][$nodeId],
				-$this->max
			);
		}
	}

	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
		$this->ensure();
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
		$this->ensure();
		unset($_SESSION['mb_memory']['nodes'][$nodeId]);
	}

	public function put(string $key, mixed $value): void {
		$this->ensure();
		$_SESSION['mb_memory']['data'][$key] = $value;
	}

	public function get(string $key): mixed {
		$this->ensure();
		return $_SESSION['mb_memory']['data'][$key] ?? null;
	}

	public function forget(string $key): void {
		$this->ensure();
		unset($_SESSION['mb_memory']['data'][$key]);
	}

	public function keys(): array {
		$this->ensure();
		return array_keys($_SESSION['mb_memory']['data'] ?? []);
	}

	public function getPriority(): int {
		return 0;
	}
}

