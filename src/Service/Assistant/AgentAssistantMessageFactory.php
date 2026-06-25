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
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace MissionBay\Service\Assistant;

use MissionBay\Api\IAgentAssistantMessageFactory;

final class AgentAssistantMessageFactory implements IAgentAssistantMessageFactory {

	public function createUserMessage(string $prompt): array {
		return [
			'id' => uniqid('msg_', true),
			'role' => 'user',
			'content' => $prompt,
			'timestamp' => (new \DateTimeImmutable())->format('c'),
			'feedback' => null
		];
	}

	public function createAssistantMessage(string $assistantMessageId, mixed $content): array {
		return [
			'id' => $assistantMessageId,
			'role' => 'assistant',
			'content' => $this->normalizeContent($content),
			'timestamp' => (new \DateTimeImmutable())->format('c'),
			'feedback' => null
		];
	}

	public function normalizeContent(mixed $content): string {
		if ($content === null) {
			return '';
		}

		if (is_string($content)) {
			return $content;
		}

		if (is_bool($content)) {
			return $content ? 'true' : 'false';
		}

		if (is_int($content) || is_float($content)) {
			return (string)$content;
		}

		$json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false || $json === 'null') {
			return '';
		}

		return $json;
	}

	public function isVisibleHistoryEntry(mixed $entry): bool {
		if (!is_array($entry) || !isset($entry['role'])) {
			return false;
		}

		$role = (string)$entry['role'];

		if ($role === 'system' || $role === 'user') {
			return true;
		}

		if ($role !== 'assistant') {
			return false;
		}

		if (!empty($entry['tool_calls']) && is_array($entry['tool_calls'])) {
			return false;
		}

		return true;
	}
}
