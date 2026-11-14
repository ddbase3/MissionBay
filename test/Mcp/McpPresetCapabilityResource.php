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
use Base3\MissionBay\Api\IAgentComponentPresetMaterializer;
use Base3\MissionBay\Api\IAgentPromptProvider;
use Base3\MissionBay\Api\IAgentResourceProvider;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Dto\AgentComponentPresetMaterialization;
use Base3\MissionBay\Mcp\McpToolPresetMaterializer;
use Base3\MissionBay\Resource\AbstractAgentResource;
use PHPUnit\Framework\TestCase;

final class McpPresetCapabilityResource extends AbstractAgentResource implements IAgentTool, IAgentResourceProvider, IAgentPromptProvider {

	public static function getName(): string {
		return 'mcppresetcapabilityresource';
	}

	public function getDescription(): string {
		return 'Test capability resource.';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'function' => [
				'name' => 'remote_tool',
				'description' => 'Remote tool.',
				'parameters' => [
					'type' => 'object',
					'properties' => []
				]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		return ['name' => $name, 'arguments' => $arguments];
	}

	public function getResourceDefinitions(IAgentContext $context): array {
		return [[
			'uri' => 'remote://resource',
			'name' => 'remote-resource'
		]];
	}

	public function readResource(string $uri, IAgentContext $context): ?array {
		return $uri === 'remote://resource'
			? ['contents' => [['uri' => $uri, 'text' => 'resource']]]
			: null;
	}

	public function getPromptDefinitions(IAgentContext $context): array {
		return [[
			'name' => 'remote_prompt',
			'description' => 'Remote prompt.'
		]];
	}

	public function getPrompt(string $name, array $arguments, IAgentContext $context): ?array {
		return $name === 'remote_prompt'
			? ['messages' => [['role' => 'user', 'content' => ['type' => 'text', 'text' => 'prompt']]]]
			: null;
	}
}
