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
