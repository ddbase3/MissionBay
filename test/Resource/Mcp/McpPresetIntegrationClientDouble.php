<?php declare(strict_types=1);

namespace MissionBay\Test\Resource\Mcp;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentMemory;
use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Api\IMcpClient;
use MissionBay\Api\IMcpClientFactory;
use MissionBay\Capability\AgentCapabilityCatalogBuilder;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\Mcp\McpClientConfig;
use MissionBay\Mcp\Client\McpRemoteToolDefinitionMapper;
use MissionBay\Mcp\Client\McpRemoteToolResultMapper;
use MissionBay\Profile\AgentAssistantToolSetupFactory;
use MissionBay\Resource\ConfiguredAgentToolResource;
use MissionBay\Resource\Mcp\McpClientAgentResource;
use MissionBay\Service\AgentComponentPresetMaterializer;
use PHPUnit\Framework\TestCase;

final class McpPresetIntegrationClientDouble implements IMcpClient {

	private string $lastToolName = '';

	/** @var array<string,mixed> */
	private array $lastArguments = [];

	public function initialize(): array {
		return [
			'protocolVersion' => '2025-11-25',
			'capabilities' => ['tools' => []],
			'serverInfo' => ['name' => 'DeepWiki test', 'version' => '1']
		];
	}

	public function getInitializeResult(): array {
		return $this->initialize();
	}

	public function listTools(): array {
		return [[
			'name' => 'read_wiki_structure',
			'description' => 'Get a list of documentation topics for a GitHub repository.',
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'repoName' => ['type' => 'string']
				],
				'required' => ['repoName']
			]
		]];
	}

	public function callTool(string $name, array $arguments = []): array {
		$this->lastToolName = $name;
		$this->lastArguments = $arguments;

		return [
			'content' => [[
				'type' => 'text',
				'text' => '# Repository Wiki Structure'
			]],
			'isError' => false
		];
	}

	public function listResources(): array {
		return [];
	}

	public function listResourceTemplates(): array {
		return [];
	}

	public function readResource(string $uri): array {
		return ['contents' => []];
	}

	public function listPrompts(): array {
		return [];
	}

	public function getPrompt(string $name, array $arguments = []): array {
		return ['messages' => []];
	}

	public function getProtocolVersion(): string {
		return '2025-11-25';
	}

	public function getSessionId(): string {
		return '';
	}

	public function getLastToolName(): string {
		return $this->lastToolName;
	}

	/** @return array<string,mixed> */
	public function getLastArguments(): array {
		return $this->lastArguments;
	}
}
