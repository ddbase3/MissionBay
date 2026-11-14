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
 * Immutable HTTP request consumed by an MCP transport implementation.
 */
final class McpHttpRequest {

	/**
	 * @param array<string,string> $headers
	 */
	public function __construct(
		private readonly string $endpoint,
		private readonly string $body,
		private readonly array $headers,
		private readonly int $connectTimeout,
		private readonly int $requestTimeout,
		private readonly int $maxResponseBytes,
		private readonly bool $verifyTls
	) {}

	public function getEndpoint(): string {
		return $this->endpoint;
	}

	public function getBody(): string {
		return $this->body;
	}

	/** @return array<string,string> */
	public function getHeaders(): array {
		return $this->headers;
	}

	public function getConnectTimeout(): int {
		return $this->connectTimeout;
	}

	public function getRequestTimeout(): int {
		return $this->requestTimeout;
	}

	public function getMaxResponseBytes(): int {
		return $this->maxResponseBytes;
	}

	public function shouldVerifyTls(): bool {
		return $this->verifyTls;
	}
}
