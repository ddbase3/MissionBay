<?php declare(strict_types=1);

namespace MissionBay\Test\Mcp;

use AssistantFoundation\Api\IAgentContext;
use Base3\Api\IClassMap;
use Base3\Logger\Api\ILogger;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentComponentPresetMaterializer;
use MissionBay\Api\IAgentTool;
use MissionBay\Api\IConfirmableAgentTool;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\AgentComponentPresetMaterialization;
use MissionBay\Mcp\McpJsonRpcHandler;
use MissionBay\Mcp\McpToolDefinitionMapper;
use MissionBay\Mcp\McpToolPresetMaterializer;
use MissionBay\Mcp\McpToolProfileRepository;
use MissionBay\Mcp\McpToolResultMapper;
use PHPUnit\Framework\TestCase;

final class McpClientAuthorizedExecutionTest extends TestCase {

	public function testToolsListContainsOnlyProfileToolsAndCompleteAnnotations(): void {
		[$handler] = $this->createHarness();
		$response = $handler->handle([
			'jsonrpc' => '2.0',
			'id' => 1,
			'method' => 'tools/list',
			'params' => []
		], 'profile-1');

		$this->assertIsArray($response);
		$tools = $response['result']['tools'];
		$this->assertSame(['list_plugins', 'set_plugin_state'], array_column($tools, 'name'));
		$this->assertNotContains('missionbay_confirm_action', array_column($tools, 'name'));
		$this->assertSame([
			'readOnlyHint' => true,
			'destructiveHint' => false,
			'idempotentHint' => true,
			'openWorldHint' => true
		], $tools[0]['annotations']);
		$this->assertSame([
			'readOnlyHint' => false,
			'destructiveHint' => true,
			'idempotentHint' => false,
			'openWorldHint' => true
		], $tools[1]['annotations']);
	}

	public function testAuthorizedMutationExecutesDirectlyWithoutServerPendingConfirmation(): void {
		[$handler, $tool] = $this->createHarness();
		$response = $handler->handle([
			'jsonrpc' => '2.0',
			'id' => 2,
			'method' => 'tools/call',
			'params' => [
				'name' => 'set_plugin_state',
				'arguments' => [
					'plugin' => 'example',
					'state' => 'inactive'
				]
			]
		], 'profile-1');

		$this->assertIsArray($response);
		$this->assertArrayNotHasKey('isError', $response['result']);
		$this->assertSame([
			'ok' => true,
			'plugin' => 'example',
			'state' => 'inactive'
		], $response['result']['structuredContent']);
		$this->assertSame(1, $tool->getCallCount());
		$this->assertSame(0, $tool->getConfirmationRequestCount());
	}

	/** @return array{McpJsonRpcHandler,ClientAuthorizedToolTestDouble} */
	private function createHarness(): array {
		$tool = new ClientAuthorizedToolTestDouble();
		$logger = $this->createStub(ILogger::class);
		$settingsStore = new ClientAuthorizedSettingsStore([
			'tool-profile' => [
				'profile-1' => [
					'id' => 'profile-1',
					'type' => 'mcp',
					'enabled' => true,
					'tools' => ['test-tool']
				]
			]
		]);
		$presetMaterializer = new ClientAuthorizedPresetMaterializer($tool);
		$materializer = new McpToolPresetMaterializer($presetMaterializer, $logger);
		$handler = new McpJsonRpcHandler(
			new McpToolProfileRepository($settingsStore),
			$materializer,
			new McpToolDefinitionMapper(),
			new McpToolResultMapper(),
			$this->createStub(IClassMap::class),
			$logger
		);

		return [$handler, $tool];
	}
}

final class ClientAuthorizedPresetMaterializer implements IAgentComponentPresetMaterializer {

	public function __construct(private readonly ClientAuthorizedToolTestDouble $tool) {}

	public function createContext(array $vars = []): IAgentContext {
		return new AgentContext(null, $vars);
	}

	public function materialize(string $presetId, IAgentContext $context): AgentComponentPresetMaterialization {
		return new AgentComponentPresetMaterialization(
			$presetId,
			[
				'id' => $presetId,
				'type' => ClientAuthorizedToolTestDouble::getName(),
				'enabled' => true,
				'capabilities' => ['tool']
			],
			null,
			$this->tool,
			null,
			null,
			['tool']
		);
	}
}

final class ClientAuthorizedToolTestDouble implements IAgentTool, IConfirmableAgentTool {

	private int $callCount = 0;
	private int $confirmationRequestCount = 0;

	public static function getName(): string {
		return 'clientauthorizedtooltestdouble';
	}

	public function getToolDefinitions(): array {
		return [
			[
				'type' => 'function',
				'label' => 'List plugins',
				'readOnlyHint' => true,
				'function' => [
					'name' => 'list_plugins',
					'description' => 'Lists plugins.',
					'parameters' => ['type' => 'object', 'properties' => []]
				]
			],
			[
				'type' => 'function',
				'label' => 'Set plugin state',
				'readOnlyHint' => false,
				'mutation' => true,
				'requiresApproval' => true,
				'function' => [
					'name' => 'set_plugin_state',
					'description' => 'Changes plugin state.',
					'parameters' => [
						'type' => 'object',
						'properties' => [
							'plugin' => ['type' => 'string'],
							'state' => ['type' => 'string']
						],
						'required' => ['plugin', 'state']
					]
				]
			]
		];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		$this->callCount++;

		if($name === 'list_plugins') {
			return ['plugins' => []];
		}

		if($name === 'set_plugin_state') {
			return [
				'ok' => true,
				'plugin' => (string)($arguments['plugin'] ?? ''),
				'state' => (string)($arguments['state'] ?? '')
			];
		}

		throw new \InvalidArgumentException('Unsupported test tool: ' . $name);
	}

	public function getConfirmationRequest(string $name, array $arguments, IAgentContext $context): ?array {
		$this->confirmationRequestCount++;

		return [
			'title' => 'Confirm mutation',
			'message' => 'Confirm the mutation.'
		];
	}

	public function getCallCount(): int {
		return $this->callCount;
	}

	public function getConfirmationRequestCount(): int {
		return $this->confirmationRequestCount;
	}
}

final class ClientAuthorizedSettingsStore implements ISettingsStore {

	/** @param array<string,array<string,array<string,mixed>>> $data */
	public function __construct(private array $data) {}

	public function get(string $group, string $name, array $default = []): array {
		return $this->data[$group][$name] ?? $default;
	}

	public function set(string $group, string $name, array $settings): void {
		$this->data[$group][$name] = $settings;
	}

	public function has(string $group, string $name): bool {
		return isset($this->data[$group][$name]);
	}

	public function remove(string $group, string $name): void {
		unset($this->data[$group][$name]);
	}

	public function getGroup(string $group): array {
		return $this->data[$group] ?? [];
	}

	public function save(): void {
	}

	public function reload(): void {
	}
}
