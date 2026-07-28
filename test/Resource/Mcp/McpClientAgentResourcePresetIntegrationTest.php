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

final class McpClientAgentResourcePresetIntegrationTest extends TestCase {

	public function testPresetIdIsAppliedByMissionBayAndRemoteNameIsPreservedForExecution(): void {
		$client = new McpPresetIntegrationClientDouble();
		$materializer = new AgentComponentPresetMaterializer(
			new McpPresetIntegrationRepositoryDouble(),
			new McpPresetIntegrationResourceFactoryDouble($client),
			new McpPresetIntegrationContextFactoryDouble(),
			new McpPresetIntegrationNullLogger()
		);
		$context = new AgentContext();
		$tool = $materializer->materialize('deepwiki', $context)->getTool();

		$this->assertInstanceOf(ConfiguredAgentToolResource::class, $tool);

		$setup = (new AgentAssistantToolSetupFactory(new AgentCapabilityCatalogBuilder()))->create(
			[$tool],
			null,
			'Show the repository documentation structure.',
			'',
			$context
		);
		$definitions = $setup->getToolDefs();

		$this->assertCount(1, $definitions);
		$this->assertSame(
			'deepwiki__read_wiki_structure',
			$definitions[0]['function']['name'] ?? null
		);
		$this->assertTrue($definitions[0]['requiresApproval'] ?? false);

		$result = $tool->callTool(
			'deepwiki__read_wiki_structure',
			['repoName' => 'modelcontextprotocol/servers'],
			$context
		);

		$this->assertSame('read_wiki_structure', $client->getLastToolName());
		$this->assertSame('modelcontextprotocol/servers', $client->getLastArguments()['repoName'] ?? null);
		$this->assertSame('# Repository Wiki Structure', $result);
	}
}

final class McpPresetIntegrationRepositoryDouble implements IAgentComponentPresetRepository {

	/** @var array<string,array<string,mixed>> */
	private array $presets = [
		'deepwiki' => [
			'id' => 'deepwiki',
			'label' => 'DeepWiki',
			'type' => 'mcpclientagentresource',
			'enabled' => true,
			'capabilities' => ['tool'],
			'config' => [
				'endpoint' => 'https://mcp.deepwiki.com/mcp',
				'auth_type' => 'none'
			],
			'docks' => []
		]
	];

	public function getPresets(): array {
		return $this->presets;
	}

	public function getPreset(string $id, array $default = []): array {
		return $this->presets[$id] ?? $default;
	}

	public function hasPreset(string $id): bool {
		return isset($this->presets[$id]);
	}

	public function savePreset(string $id, array $preset): void {
		$this->presets[$id] = $preset;
	}

	public function removePreset(string $id): void {
		unset($this->presets[$id]);
	}
}

final class McpPresetIntegrationResourceFactoryDouble implements IAgentResourceFactory {

	public function __construct(private readonly McpPresetIntegrationClientDouble $client) {}

	public function createResource(string $type): ?IAgentResource {
		return match($type) {
			McpClientAgentResource::getName() => new McpClientAgentResource(
				new McpPresetIntegrationClientFactoryDouble($this->client),
				new McpPresetIntegrationConfigResolverDouble(),
				new McpRemoteToolDefinitionMapper(),
				new McpRemoteToolResultMapper(),
				new McpPresetIntegrationNullLogger()
			),
			ConfiguredAgentToolResource::getName() => new ConfiguredAgentToolResource(
				new McpPresetIntegrationConfigResolverDouble(),
				new McpPresetIntegrationEventManagerDouble()
			),
			default => null
		};
	}
}

final class McpPresetIntegrationContextFactoryDouble implements IAgentContextFactory {

	public function createContext(
		string $type = 'agentcontext',
		?IAgentMemory $memory = null,
		array $vars = []
	): IAgentContext {
		return new AgentContext($memory, $vars);
	}
}

final class McpPresetIntegrationConfigResolverDouble implements IAgentConfigValueResolver {

	public function resolveValue(array|string|int|float|bool|null $config): mixed {
		return $config;
	}
}

final class McpPresetIntegrationClientFactoryDouble implements IMcpClientFactory {

	public function __construct(private readonly IMcpClient $client) {}

	public function create(McpClientConfig $config): IMcpClient {
		return $this->client;
	}
}

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

final class McpPresetIntegrationEventManagerDouble implements IEventManager {

	public function on(string $event, callable $listener, int $priority = 0): void {}
	public function once(string $event, callable $listener, int $priority = 0): void {}
	public function off(string $event, callable $listener): void {}
	public function fire(object|string $event, ...$args): array { return []; }
}

final class McpPresetIntegrationNullLogger implements ILogger {

	public function emergency(string|\Stringable $message, array $context = []): void {}
	public function alert(string|\Stringable $message, array $context = []): void {}
	public function critical(string|\Stringable $message, array $context = []): void {}
	public function error(string|\Stringable $message, array $context = []): void {}
	public function warning(string|\Stringable $message, array $context = []): void {}
	public function notice(string|\Stringable $message, array $context = []): void {}
	public function info(string|\Stringable $message, array $context = []): void {}
	public function debug(string|\Stringable $message, array $context = []): void {}
	public function logLevel(string $level, string|\Stringable $message, array $context = []): void {}
	public function log(string $scope, string $log, ?int $timestamp = null): bool { return true; }
	public function getScopes(): array { return []; }
	public function getNumOfScopes(): int { return 0; }
	public function getLogs(string $scope, int $num = 50, bool $reverse = true): array { return []; }
}
