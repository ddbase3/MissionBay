<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace MissionBay\Memory;

use AssistantFoundation\Api\IAgentConversationMemory;
use Base3\Session\Api\ISession;

/**
 * Small session-backed conversation history for legacy direct flow usage.
 *
 * Values are persisted as scalar serialized strings through ISession. This
 * avoids direct PHP-global access and works with host session adapters that do
 * not preserve nested arrays reliably.
 */
class SessionMemory implements IAgentConversationMemory {

	private const NODE_KEY_PREFIX = 'mb_session_memory_node_';
	private const LEGACY_KEY = 'mb_memory';

	private int $max = 20;

	public function __construct(private readonly ISession $session) {}

	public static function getName(): string {
		return 'sessionmemory';
	}

	public function loadNodeHistory(string $nodeId): array {
		if (!$this->ensureStarted()) {
			return [];
		}

		$history = $this->readNode($nodeId);
		if ($history !== []) {
			return $history;
		}

		$legacy = $this->session->get(self::LEGACY_KEY, []);
		$legacyHistory = is_array($legacy)
			? ($legacy['nodes'][$nodeId] ?? [])
			: [];

		if (!is_array($legacyHistory) || $legacyHistory === []) {
			return [];
		}

		$history = array_values($legacyHistory);
		$this->writeNode($nodeId, $history);

		return $history;
	}

	public function appendNodeHistory(string $nodeId, array $message): void {
		if (!$this->ensureStarted()) {
			return;
		}

		$history = $this->loadNodeHistory($nodeId);
		$history[] = $message;

		if (count($history) > $this->max) {
			$history = array_values(array_slice($history, -$this->max));
		}

		$this->writeNode($nodeId, $history);
	}

	public function setFeedback(string $nodeId, string $messageId, ?string $feedback): bool {
		if (!$this->ensureStarted()) {
			return false;
		}

		$history = $this->loadNodeHistory($nodeId);
		foreach ($history as &$entry) {
			if (!is_array($entry) || ($entry['id'] ?? null) !== $messageId) {
				continue;
			}

			$entry['feedback'] = $feedback;
			unset($entry);
			$this->writeNode($nodeId, $history);
			return true;
		}
		unset($entry);

		return false;
	}

	public function resetNodeHistory(string $nodeId): void {
		if (!$this->ensureStarted()) {
			return;
		}

		$this->session->remove($this->nodeKey($nodeId));

		$legacy = $this->session->get(self::LEGACY_KEY, []);
		if (!is_array($legacy) || !isset($legacy['nodes'][$nodeId])) {
			return;
		}

		unset($legacy['nodes'][$nodeId]);
		$this->session->set(self::LEGACY_KEY, $legacy);
	}

	public function getPriority(): int {
		return 0;
	}

	private function ensureStarted(): bool {
		return $this->session->started() || $this->session->start();
	}

	private function nodeKey(string $nodeId): string {
		return self::NODE_KEY_PREFIX . hash('sha256', $nodeId);
	}

	private function readNode(string $nodeId): array {
		$value = $this->session->get($this->nodeKey($nodeId), '');
		if (!is_string($value) || $value === '') {
			return [];
		}

		$serialized = base64_decode($value, true);
		if (!is_string($serialized)) {
			return [];
		}

		$history = @unserialize($serialized, ['allowed_classes' => false]);

		return is_array($history) ? array_values($history) : [];
	}

	private function writeNode(string $nodeId, array $history): void {
		$this->session->set(
			$this->nodeKey($nodeId),
			base64_encode(serialize(array_values($history)))
		);
	}
}
