<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentMemory;
use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentCapabilitySourceMetadata;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Api\IAgentTool;
use MissionBay\Context\AgentContext;
use MissionBay\Resource\AbstractAgentResource;
use MissionBay\Resource\ConfiguredAgentToolResource;
use MissionBay\Service\AgentComponentPresetMaterializer;
use PHPUnit\Framework\TestCase;

final class AgentComponentPresetMaterializerToolPrefixTest extends TestCase {

	public function testPresetIdPrefixesDefinitionsAndIsRemovedBeforeExecution(): void {
		$remoteTool = new MaterializerRemoteToolDouble();
		$materializer = new AgentComponentPresetMaterializer(
			new MaterializerPresetRepositoryDouble(),
			new MaterializerResourceFactoryDouble($remoteTool),
			new MaterializerContextFactoryDouble(),
			new MaterializerNullLogger()
		);
		$context = new AgentContext();
		$materialization = $materializer->materialize('deepwiki', $context);
		$tool = $materialization->getTool();

		$this->assertInstanceOf(ConfiguredAgentToolResource::class, $tool);
		$this->assertSame(
			'deepwiki__read_wiki_structure',
			$tool->getToolDefinitions()[0]['function']['name'] ?? null
		);
		$this->assertInstanceOf(IAgentCapabilitySourceMetadata::class, $tool);
		$this->assertSame('deepwiki', $tool->getCapabilitySourceId());
		$this->assertSame('DeepWiki', $tool->getCapabilitySourceLabel());
		$this->assertSame(
			'Remote MCP-like tool used to verify configured preset naming.',
			$tool->getCapabilitySourceDescription()
		);

		$result = $tool->callTool(
			'deepwiki__read_wiki_structure',
			['repoName' => 'modelcontextprotocol/servers'],
			$context
		);

		$this->assertSame('read_wiki_structure', $remoteTool->getLastName());
		$this->assertSame(['ok' => true], $result);
	}
}

final class MaterializerPresetRepositoryDouble implements IAgentComponentPresetRepository {

	private array $presets = [
		'deepwiki' => [
			'id' => 'deepwiki',
			'label' => 'DeepWiki',
			'type' => 'materializerremotetooldouble',
			'enabled' => true,
			'capabilities' => ['tool'],
			'config' => [],
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

final class MaterializerResourceFactoryDouble implements IAgentResourceFactory {

	public function __construct(private readonly MaterializerRemoteToolDouble $remoteTool) {}

	public function createResource(string $type): ?IAgentResource {
		return match($type) {
			MaterializerRemoteToolDouble::getName() => $this->remoteTool,
			ConfiguredAgentToolResource::getName() => new ConfiguredAgentToolResource(
				new MaterializerIdentityResolverDouble(),
				new MaterializerEventManagerDouble()
			),
			default => null
		};
	}
}

final class MaterializerContextFactoryDouble implements IAgentContextFactory {

	public function createContext(
		string $type = 'agentcontext',
		?IAgentMemory $memory = null,
		array $vars = []
	): IAgentContext {
		return new AgentContext($memory, $vars);
	}
}

final class MaterializerIdentityResolverDouble implements IAgentConfigValueResolver {

	public function resolveValue(array|string|int|float|bool|null $config): mixed {
		if(is_array($config) && ($config['mode'] ?? null) === 'fixed') {
			return $config['value'] ?? null;
		}

		return $config;
	}
}

final class MaterializerEventManagerDouble implements IEventManager {

	public function on(string $event, callable $listener, int $priority = 0): void {
	}

	public function once(string $event, callable $listener, int $priority = 0): void {
	}

	public function off(string $event, callable $listener): void {
	}

	public function fire(object|string $event, ...$args): array {
		return [];
	}
}

final class MaterializerRemoteToolDouble extends AbstractAgentResource implements IAgentTool {

	private string $lastName = '';

	public static function getName(): string {
		return 'materializerremotetooldouble';
	}

	public function getDescription(): string {
		return 'Remote MCP-like tool used to verify configured preset naming.';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'readOnlyHint' => true,
			'mutation' => false,
			'requiresApproval' => false,
			'function' => [
				'name' => 'read_wiki_structure',
				'description' => 'Reads a repository documentation structure.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'repoName' => ['type' => 'string']
					],
					'required' => ['repoName']
				]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		$this->lastName = $name;
		return ['ok' => true];
	}

	public function getLastName(): string {
		return $this->lastName;
	}
}

final class MaterializerNullLogger implements ILogger {

	public function emergency(string|\Stringable $message, array $context = []): void {
	}

	public function alert(string|\Stringable $message, array $context = []): void {
	}

	public function critical(string|\Stringable $message, array $context = []): void {
	}

	public function error(string|\Stringable $message, array $context = []): void {
	}

	public function warning(string|\Stringable $message, array $context = []): void {
	}

	public function notice(string|\Stringable $message, array $context = []): void {
	}

	public function info(string|\Stringable $message, array $context = []): void {
	}

	public function debug(string|\Stringable $message, array $context = []): void {
	}

	public function logLevel(string $level, string|\Stringable $message, array $context = []): void {
	}

	public function log(string $scope, string $log, ?int $timestamp = null): bool {
		return true;
	}

	public function getScopes(): array {
		return [];
	}

	public function getNumOfScopes(): int {
		return 0;
	}

	public function getLogs(string $scope, int $num = 50, bool $reverse = true): array {
		return [];
	}
}
