<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp\Client;

use MissionBay\Api\IMcpTransport;
use MissionBay\Dto\Mcp\McpClientConfig;
use MissionBay\Dto\Mcp\McpHttpRequest;
use MissionBay\Dto\Mcp\McpHttpResponse;
use MissionBay\Mcp\Client\McpClient;
use MissionBay\Mcp\Client\McpClientException;
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
