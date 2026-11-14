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

namespace Base3\MissionBay\Dto\Mcp;

/**
 * Immutable HTTP response returned by an MCP transport implementation.
 */
final class McpHttpResponse {

	/** @var array<string,string> */
	private readonly array $headers;

	/**
	 * @param array<string,string> $headers
	 */
	public function __construct(
		private readonly int $statusCode,
		array $headers,
		private readonly string $body
	) {
		$normalized = [];

		foreach($headers as $name => $value) {
			$name = strtolower(trim((string)$name));
			if($name === '') {
				continue;
			}
			$normalized[$name] = (string)$value;
		}

		$this->headers = $normalized;
	}

	public function getStatusCode(): int {
		return $this->statusCode;
	}

	/** @return array<string,string> */
	public function getHeaders(): array {
		return $this->headers;
	}

	public function getHeader(string $name, string $default = ''): string {
		return $this->headers[strtolower(trim($name))] ?? $default;
	}

	public function getBody(): string {
		return $this->body;
	}

	public function getContentType(): string {
		$contentType = $this->getHeader('content-type');
		$separator = strpos($contentType, ';');

		if($separator !== false) {
			$contentType = substr($contentType, 0, $separator);
		}

		return strtolower(trim($contentType));
	}
}
