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
use Base3\MissionBay\Mcp\Client\McpHmacRequestSigner;
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
