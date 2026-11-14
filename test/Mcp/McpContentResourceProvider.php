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

namespace Base3\MissionBay\Test\Mcp;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MissionBay\Api\IAgentPromptProvider;
use Base3\MissionBay\Api\IAgentResourceProvider;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Mcp\McpPromptCatalog;
use Base3\MissionBay\Mcp\McpResourceCatalog;
use PHPUnit\Framework\TestCase;

final class McpContentResourceProvider implements IAgentResourceProvider {

	public function __construct(private readonly string $name) {}

	public static function getName(): string {
		return 'mcpcontentresourceprovider';
	}

	public function getResourceDefinitions(IAgentContext $context): array {
		return [
			[
				'uri' => 'repo://image',
				'name' => 'image',
				'title' => 'Repository image',
				'mimeType' => 'image/png',
				'size' => 42,
				'annotations' => ['audience' => ['assistant']],
				'icons' => [['src' => 'icon.svg']],
				'_meta' => ['provider' => $this->name]
			],
			[
				'uriTemplate' => 'repo://file/{path}',
				'name' => 'file',
				'title' => 'Repository file',
				'mimeType' => 'application/octet-stream'
			]
		];
	}

	public function readResource(string $uri, IAgentContext $context): ?array {
		if($uri !== 'repo://image') {
			return null;
		}

		return [
			'contents' => [[
				'uri' => $uri,
				'mimeType' => 'image/png',
				'blob' => 'base64-data',
				'_meta' => ['source' => 'remote']
			]],
			'_meta' => ['request' => 'metadata']
		];
	}
}
