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

namespace Base3\MissionBay\Mcp\Client;

/**
 * Converts MCP tool call results into values consumed by MissionBay agents.
 *
 * Only the protocol-level isError flag classifies a tools/call result as a
 * failed operation. Text content is never interpreted heuristically.
 */
final class McpRemoteToolResultMapper {

	public function toAgentResult(array $result): mixed {
		if($this->isErrorResult($result)) {
			throw new \RuntimeException($this->getErrorMessage($result));
		}

		if(is_array($result['structuredContent'] ?? null)) {
			return $result['structuredContent'];
		}

		$content = is_array($result['content'] ?? null) ? $result['content'] : [];
		$text = [];
		$hasNonText = false;

		foreach($content as $item) {
			if(!is_array($item)) {
				$hasNonText = true;
				continue;
			}

			if(($item['type'] ?? '') === 'text') {
				$text[] = (string)($item['text'] ?? '');
			}
			else {
				$hasNonText = true;
			}
		}

		if($hasNonText) {
			return $result;
		}

		if($text !== []) {
			return implode("\n", $text);
		}

		return $result;
	}

	/** @param array<string,mixed> $result */
	private function isErrorResult(array $result): bool {
		return ($result['isError'] ?? false) === true;
	}

	/** @param array<string,mixed> $result */
	private function getErrorMessage(array $result): string {
		$content = is_array($result['content'] ?? null) ? $result['content'] : [];
		$messages = [];

		foreach($content as $item) {
			if(!is_array($item) || ($item['type'] ?? '') !== 'text') {
				continue;
			}

			$message = trim((string)($item['text'] ?? ''));
			if($message !== '') {
				$messages[] = $message;
			}
		}

		if($messages !== []) {
			return implode("\n", $messages);
		}

		$structured = $result['structuredContent'] ?? null;
		if(is_array($structured) && $structured !== []) {
			$json = json_encode($structured, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			if(is_string($json) && $json !== '') {
				return $json;
			}
		}

		return 'Remote MCP tool execution failed.';
	}
}
