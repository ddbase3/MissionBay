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

final class McpContentPromptProvider implements IAgentPromptProvider {

	public function __construct(private readonly string $providerName) {}

	public static function getName(): string {
		return 'mcpcontentpromptprovider';
	}

	public function getPromptDefinitions(IAgentContext $context): array {
		return [[
			'name' => 'review_image',
			'title' => 'Review image',
			'description' => 'Reviews one image.',
			'icons' => [['src' => 'prompt.svg']],
			'_meta' => ['provider' => $this->providerName]
		]];
	}

	public function getPrompt(string $name, array $arguments, IAgentContext $context): ?array {
		if($name !== 'review_image') {
			return null;
		}

		return [
			'messages' => [[
				'role' => 'user',
				'content' => [
					'type' => 'image',
					'data' => 'image-data',
					'mimeType' => 'image/png'
				]
			]],
			'_meta' => ['prompt' => 'metadata']
		];
	}
}
