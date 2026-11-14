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

final class McpClientQueueTransport implements IMcpTransport {

	/** @var array<int,McpHttpResponse> */
	private array $responses;

	/** @var array<int,McpHttpRequest> */
	private array $requests = [];

	/** @param array<int,McpHttpResponse> $responses */
	public function __construct(array $responses) {
		$this->responses = array_values($responses);
	}

	public function send(McpHttpRequest $request): McpHttpResponse {
		$this->requests[] = $request;
		$response = array_shift($this->responses);

		if(!$response instanceof McpHttpResponse) {
			throw new \RuntimeException('No queued MCP response.');
		}

		return $response;
	}

	/** @return array<int,McpHttpRequest> */
	public function getRequests(): array {
		return $this->requests;
	}

	/** @return array<string,mixed> */
	public function getDecodedRequest(int $index): array {
		return json_decode($this->requests[$index]->getBody(), true, 512, JSON_THROW_ON_ERROR);
	}
}
