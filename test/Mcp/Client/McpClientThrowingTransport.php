<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp\Client;

use MissionBay\Api\IMcpTransport;
use MissionBay\Dto\Mcp\McpClientConfig;
use MissionBay\Dto\Mcp\McpHttpRequest;
use MissionBay\Dto\Mcp\McpHttpResponse;
use MissionBay\Mcp\Client\McpClient;
use MissionBay\Mcp\Client\McpClientException;
use PHPUnit\Framework\TestCase;

final class McpClientThrowingTransport implements IMcpTransport {

	public function __construct(private readonly \Throwable $exception) {}

	public function send(McpHttpRequest $request): McpHttpResponse {
		throw $this->exception;
	}
}
