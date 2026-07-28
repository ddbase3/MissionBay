<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp\Client;

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
