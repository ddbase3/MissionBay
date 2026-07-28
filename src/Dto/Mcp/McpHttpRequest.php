<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Dto\Mcp;

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
