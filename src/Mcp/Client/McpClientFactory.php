<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp\Client;

use MissionBay\Api\IMcpClient;
use MissionBay\Api\IMcpClientFactory;
use MissionBay\Api\IMcpTransport;
use MissionBay\Dto\Mcp\McpClientConfig;

/**
 * Default factory for run-local MCP clients.
 */
final class McpClientFactory implements IMcpClientFactory {

	public function __construct(
		private readonly IMcpTransport $transport,
		private readonly McpHmacRequestSigner $hmacRequestSigner
	) {}

	public static function getName(): string {
		return 'mcpclientfactory';
	}

	public function create(McpClientConfig $config): IMcpClient {
		return new McpClient($config, $this->transport, $this->hmacRequestSigner);
	}
}
