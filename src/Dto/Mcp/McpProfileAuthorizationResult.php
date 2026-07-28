<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Dto\Mcp;

/**
 * Result of authorizing one HTTP request for one MCP profile.
 */
final class McpProfileAuthorizationResult {

	private function __construct(
		private readonly bool $authorized,
		private readonly int $statusCode,
		private readonly string $mode,
		private readonly string $failureCode
	) {}

	public static function success(string $mode): self {
		return new self(true, 200, $mode, '');
	}

	public static function failure(int $statusCode, string $failureCode): self {
		return new self(false, $statusCode, '', $failureCode);
	}

	public function isAuthorized(): bool {
		return $this->authorized;
	}

	public function getStatusCode(): int {
		return $this->statusCode;
	}

	public function getMode(): string {
		return $this->mode;
	}

	public function getFailureCode(): string {
		return $this->failureCode;
	}
}
