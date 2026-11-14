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
 * Normalized MCP transport or JSON-RPC failure.
 */
final class McpClientException extends \RuntimeException {

	public function __construct(
		string $message,
		private readonly int $httpStatus = 0,
		private readonly ?int $jsonRpcCode = null,
		?\Throwable $previous = null
	) {
		parent::__construct($message, $jsonRpcCode ?? $httpStatus, $previous);
	}

	public function getHttpStatus(): int {
		return $this->httpStatus;
	}

	public function getJsonRpcCode(): ?int {
		return $this->jsonRpcCode;
	}
}
