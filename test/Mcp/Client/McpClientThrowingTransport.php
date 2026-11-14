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

namespace Base3\MissionBay\Test\Mcp\Client;

use Base3\MissionBay\Api\IMcpTransport;
use Base3\MissionBay\Dto\Mcp\McpClientConfig;
use Base3\MissionBay\Dto\Mcp\McpHttpRequest;
use Base3\MissionBay\Dto\Mcp\McpHttpResponse;
use Base3\MissionBay\Mcp\Client\McpClient;
use Base3\MissionBay\Mcp\Client\McpClientException;
use PHPUnit\Framework\TestCase;

final class McpClientThrowingTransport implements IMcpTransport {

	public function __construct(private readonly \Throwable $exception) {}

	public function send(McpHttpRequest $request): McpHttpResponse {
		throw $this->exception;
	}
}
