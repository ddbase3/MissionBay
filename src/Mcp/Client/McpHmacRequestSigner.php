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

use Base3\MissionBay\Dto\Mcp\McpClientConfig;

/**
 * Creates KeyHarbor-compatible HMAC-SHA256 request headers.
 */
final class McpHmacRequestSigner {

	/**
	 * @return array<string,string>
	 */
	public function createHeaders(
		McpClientConfig $config,
		string $method,
		string $body,
		?int $timestamp = null,
		?string $nonce = null
	): array {
		if(!$config->usesHmacAuthentication()) {
			return [];
		}

		$parts = parse_url($config->getEndpoint());
		$path = is_array($parts) ? (string)($parts['path'] ?? '/') : '/';
		$queryString = is_array($parts) ? (string)($parts['query'] ?? '') : '';

		if($path === '') {
			$path = '/';
		}

		$timestamp ??= time();
		$nonce = trim((string)($nonce ?? bin2hex(random_bytes(16))));

		if($timestamp <= 0) {
			throw new \InvalidArgumentException('MCP HMAC timestamp must be positive.');
		}
		if($nonce === '') {
			throw new \InvalidArgumentException('MCP HMAC nonce must not be empty.');
		}

		$canonicalRequest = implode("\n", [
			strtoupper(trim($method)),
			$path,
			$queryString,
			(string)$timestamp,
			$nonce,
			hash('sha256', $body)
		]);
		$signature = hash_hmac('sha256', $canonicalRequest, $config->getHmacSecret());

		return [
			'X-BASE3-Timestamp' => (string)$timestamp,
			'X-BASE3-Nonce' => $nonce,
			'X-BASE3-Signature' => $signature
		];
	}
}
