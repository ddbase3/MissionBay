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
