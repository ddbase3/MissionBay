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

use Base3\MissionBay\Api\IMcpClient;
use Base3\MissionBay\Api\IMcpClientFactory;
use Base3\MissionBay\Api\IMcpTransport;
use Base3\MissionBay\Dto\Mcp\McpClientConfig;

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
