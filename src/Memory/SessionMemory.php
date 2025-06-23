<?php declare(strict_types=1);

namespace MissionBay\Memory;

use MissionBay\Api\IAgentMemory;
use Base3\Session\Api\ISession;

class SessionMemory implements IAgentMemory {

	private int $max = 5;

	public function __construct(private readonly ISession $session) {}

	public static function getName(): string {
		return 'sessionmemory';
	}

	public function load(string $userId): array {
		if (!$this->session->started()) return [];
		return $_SESSION['mb_memory'][$userId] ?? [];
	}

	public function remember(string $userId, string $role, string $text): void {
		if (!$this->session->started()) return;

		$_SESSION['mb_memory'][$userId][] = [$role, $text];

		if (count($_SESSION['mb_memory'][$userId]) > $this->max) {
			array_shift($_SESSION['mb_memory'][$userId]);
		}
	}

	public function reset(string $userId): void {
		if (!$this->session->started()) return;
		unset($_SESSION['mb_memory'][$userId]);
	}
}

