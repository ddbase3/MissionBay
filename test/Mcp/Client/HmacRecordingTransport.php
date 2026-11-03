<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp\Client;

use MissionBay\Api\IMcpTransport;
use MissionBay\Dto\Mcp\McpClientConfig;
use MissionBay\Dto\Mcp\McpHttpRequest;
use MissionBay\Dto\Mcp\McpHttpResponse;
use MissionBay\Mcp\Client\McpClient;
use MissionBay\Mcp\Client\McpHmacRequestSigner;
use PHPUnit\Framework\TestCase;

final class HmacRecordingTransport implements IMcpTransport {

	/** @var array<int,McpHttpRequest> */
	public array $requests = [];

	public function send(McpHttpRequest $request): McpHttpResponse {
		$this->requests[] = $request;
		$payload = json_decode($request->getBody(), true, 512, JSON_THROW_ON_ERROR);
		$method = (string)($payload['method'] ?? '');

		if($method === 'initialize') {
			return new McpHttpResponse(200, ['content-type' => 'application/json'], json_encode([
				'jsonrpc' => '2.0',
				'id' => $payload['id'],
				'result' => [
					'protocolVersion' => '2025-11-25',
					'capabilities' => ['tools' => ['listChanged' => false]],
					'serverInfo' => ['name' => 'Test', 'version' => '1.0.0']
				]
			], JSON_THROW_ON_ERROR));
		}

		if($method === 'notifications/initialized') {
			return new McpHttpResponse(202, [], '');
		}

		return new McpHttpResponse(200, ['content-type' => 'application/json'], json_encode([
			'jsonrpc' => '2.0',
			'id' => $payload['id'],
			'result' => ['tools' => []]
		], JSON_THROW_ON_ERROR));
	}
}
