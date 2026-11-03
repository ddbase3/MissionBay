<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp;

use AssistantFoundation\Api\IAgentContext;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentPromptProvider;
use MissionBay\Api\IAgentResourceProvider;
use MissionBay\Context\AgentContext;
use MissionBay\Mcp\McpPromptCatalog;
use MissionBay\Mcp\McpResourceCatalog;
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
