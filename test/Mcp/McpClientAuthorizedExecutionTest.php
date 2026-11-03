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

require_once __DIR__ . '/ClientAuthorizedPresetMaterializer.php';
require_once __DIR__ . '/ClientAuthorizedToolTestDouble.php';
require_once __DIR__ . '/ClientAuthorizedSettingsStore.php';
