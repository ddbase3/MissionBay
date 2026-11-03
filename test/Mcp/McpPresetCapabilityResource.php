<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp;

use AssistantFoundation\Api\IAgentContext;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentComponentPresetMaterializer;
use MissionBay\Api\IAgentPromptProvider;
use MissionBay\Api\IAgentResourceProvider;
use MissionBay\Api\IAgentTool;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\AgentComponentPresetMaterialization;
use MissionBay\Mcp\McpToolPresetMaterializer;
use MissionBay\Resource\AbstractAgentResource;
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
