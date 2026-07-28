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

final class McpToolPresetMaterializerTest extends TestCase {

	public function testMaterializesToolsResourcesAndPromptsFromOnePresetInstance(): void {
		$resource = new McpPresetCapabilityResource('remote-preset');
		$presetMaterializer = new McpPresetMaterializerDouble($resource);
		$materializer = new McpToolPresetMaterializer(
			$presetMaterializer,
			new McpPresetMaterializerNullLogger()
		);
		$context = new AgentContext();

		$capabilities = $materializer->materializeCapabilities([
			'tools' => ['remote-preset', 'remote-preset']
		], $context);

		$this->assertCount(1, $capabilities['tools']);
		$this->assertCount(1, $capabilities['resourceProviders']);
		$this->assertCount(1, $capabilities['promptProviders']);
		$this->assertSame($resource, $capabilities['tools'][0]);
		$this->assertSame($resource, $capabilities['resourceProviders'][0]);
		$this->assertSame($resource, $capabilities['promptProviders'][0]);
		$this->assertSame(1, $presetMaterializer->getMaterializeCalls());
		$this->assertSame([], $materializer->getWarnings());
		$this->assertSame([$resource], $materializer->materialize([
			'tools' => ['remote-preset']
		], $context));
	}
}

final class McpPresetMaterializerDouble implements IAgentComponentPresetMaterializer {

	private int $materializeCalls = 0;

	public function __construct(private readonly McpPresetCapabilityResource $resource) {}

	public function createContext(array $vars = []): IAgentContext {
		return new AgentContext(null, $vars);
	}

	public function materialize(string $presetId, IAgentContext $context): AgentComponentPresetMaterialization {
		$this->materializeCalls++;

		return new AgentComponentPresetMaterialization(
			$presetId,
			[
				'id' => $presetId,
				'type' => McpPresetCapabilityResource::getName(),
				'enabled' => true,
				'capabilities' => ['tool']
			],
			$this->resource,
			$this->resource,
			null,
			null,
			['tool'],
			[],
			[]
		);
	}

	public function getMaterializeCalls(): int {
		return $this->materializeCalls;
	}
}

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

final class McpPresetMaterializerNullLogger implements ILogger {

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
