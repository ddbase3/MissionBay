<?php declare(strict_types=1);

namespace MissionBay\Memory;

use MissionBay\Api\IAgentMemory;
use Base3\Session\Api\ISession;

class SessionMemory implements IAgentMemory
{
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
	}

	public function loadNodeHistory(string $nodeId): array {
		$this->ensure();
		return $_SESSION['mb_memory']['nodes'][$nodeId] ?? [];
	}

	public function appendNodeHistory(string $nodeId, string $role, string $text): void {
		$this->ensure();
		$_SESSION['mb_memory']['nodes'][$nodeId][] = [$role, $text];

		if (count($_SESSION['mb_memory']['nodes'][$nodeId]) > $this->max) {
			array_shift($_SESSION['mb_memory']['nodes'][$nodeId]);
		}
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
}

