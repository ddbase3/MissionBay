<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentExecutionRequest;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentFlow;
use MissionBay\Api\IAgentFlowCompiler;
use MissionBay\Api\IAgentFlowFactory;
use MissionBay\Dto\AgentFlowCompilation;
use MissionBay\Service\AgentComponentFlowBuilder;
use MissionBay\Service\AgentExecutionService;
use MissionBay\Service\AgentFlowCompiler;
use PHPUnit\Framework\TestCase;

final class AgentExecutionServiceCapabilityConfigTest extends TestCase {

	public function testCompilerBuildsCanonicalChatModelPresetFlow(): void {
		$flow = $this->createCompiler()->compile([
			'chatmodel' => 'chat-main'
		])->getFlow();

		$this->assertSame([[
			'id' => 'assistant',
			'type' => 'aiassistantnode',
			'docks' => [
				'chatmodel' => ['preset_chat_main']
			]
		]], $flow['nodes']);
		$this->assertSame([[
			'id' => 'preset_chat_main',
			'type' => 'configuredchatmodelagentresource',
			'config' => [
				'service' => [
					'mode' => 'fixed',
					'value' => 'llm-a'
				]
			]
		]], $flow['resources']);
		$this->assertSame([
			[
				'from' => '__input__',
				'output' => 'system',
				'to' => 'assistant',
				'input' => 'system'
			],
			[
				'from' => '__input__',
				'output' => 'prompt',
				'to' => 'assistant',
				'input' => 'prompt'
			]
		], $flow['connections']);
	}

	public function testHighLevelCapabilitySettingsAreAppliedToCanonicalAssistantNode(): void {
		$flow = $this->createCompiler()->compile([
			'chatmodel' => 'chat-main',
			'capability_sources' => [
				'tools' => ['internal-rag'],
				'providers' => ['github-mcp'],
				'modules' => ['coding-style']
			],
			'capability_selection' => [
				'max_tools' => 12,
				'include_tags' => ['crm']
			]
		])->getFlow();

		$this->assertSame('assistant', $flow['nodes'][0]['id']);
		$this->assertSame(['internal-rag'], $flow['nodes'][0]['inputs']['capabilitysources']['tools']);
		$this->assertSame(['github-mcp'], $flow['nodes'][0]['inputs']['capabilitysources']['providers']);
		$this->assertSame(['coding-style'], $flow['nodes'][0]['inputs']['capabilitysources']['modules']);
		$this->assertSame(12, $flow['nodes'][0]['inputs']['capabilityselection']['max_tools']);
		$this->assertSame(['crm'], $flow['nodes'][0]['inputs']['capabilityselection']['include_tags']);
	}

	public function testHistoricalFlowAndLlmSettingsCannotAlterCanonicalFlow(): void {
		$compilation = $this->createCompiler()->compile([
			'chatmodel' => 'chat-main',
			'llm' => 'historical-llm-service',
			'agent_components_assistant_node' => 'historical',
			'agent_flow' => [
				'nodes' => [[
					'id' => 'historical',
					'type' => 'streamingaiassistantnode'
				]],
				'resources' => [[
					'id' => 'historical-llm',
					'type' => 'historical-resource'
				]],
				'connections' => []
			]
		]);
		$flow = $compilation->getFlow();

		$this->assertCount(1, $flow['nodes']);
		$this->assertSame('assistant', $flow['nodes'][0]['id']);
		$this->assertSame('aiassistantnode', $flow['nodes'][0]['type']);
		$this->assertSame(['preset_chat_main'], $flow['nodes'][0]['docks']['chatmodel']);
		$this->assertCount(1, $flow['resources']);
		$this->assertSame('preset_chat_main', $flow['resources'][0]['id']);
		$this->assertSame('llm-a', $flow['resources'][0]['config']['service']['value']);
		$this->assertSame([], $compilation->getWarnings());
	}

	public function testCompilerRejectsMissingChatModelPreset(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Chat model preset is required.');

		$this->createCompiler()->compile([]);
	}

	public function testNormalExecutionDoesNotAddResumeConnection(): void {
		$service = $this->createExecutionService(false);

		$service->execute(new AgentExecutionRequest(
			$this->minimalAgentSettings(),
			['prompt' => 'Hello']
		));
	}

	public function testResumeExecutionAddsResumeConnection(): void {
		$service = $this->createExecutionService(true);

		$service->execute(new AgentExecutionRequest(
			$this->minimalAgentSettings(),
			[
				'resume' => [
					'resume_handle' => str_repeat('a', 43),
					'response_text' => 'go',
					'responses' => []
				]
			]
		));
	}

	public function testSuggestionsModeIsConnectedToAssistantNode(): void {
		$service = $this->createExecutionService(false, true);

		$service->execute(new AgentExecutionRequest(
			$this->minimalAgentSettings(),
			[
				'prompt' => 'Generate suggestions.',
				'mode' => 'suggestions'
			]
		));
	}

	private function createCompiler(): AgentFlowCompiler {
		return new AgentFlowCompiler(new AgentComponentFlowBuilder($this->componentPresetRepository()));
	}

	private function componentPresetRepository(): IAgentComponentPresetRepository {
		return new class implements IAgentComponentPresetRepository {
			private array $presets = [
				'chat-main' => [
					'id' => 'chat-main',
					'label' => 'Primary chat model',
					'type' => 'configuredchatmodelagentresource',
					'enabled' => true,
					'capabilities' => ['chatmodel'],
					'config' => [
						'service' => [
							'mode' => 'fixed',
							'value' => 'llm-a'
						]
					]
				]
			];

			public function getPresets(): array { return $this->presets; }
			public function getPreset(string $id, array $default = []): array { return $this->presets[$id] ?? $default; }
			public function hasPreset(string $id): bool { return isset($this->presets[$id]); }
			public function savePreset(string $id, array $preset): void { $this->presets[$id] = $preset; }
			public function removePreset(string $id): void { unset($this->presets[$id]); }
		};
	}

	private function createExecutionService(bool $expectResumeConnection, bool $expectModeConnection = false): AgentExecutionService {
		$context = $this->createMock(IAgentContext::class);
		$contextFactory = $this->createMock(IAgentContextFactory::class);
		$contextFactory->method('createContext')->willReturn($context);

		$flow = $this->createMock(IAgentFlow::class);
		$flow->method('run')->willReturn([]);

		$flowFactory = $this->createMock(IAgentFlowFactory::class);
		$flowFactory->expects($this->once())
			->method('createFromArray')
			->with(
				'strictflow',
				$this->callback(function(array $effectiveFlow) use ($expectResumeConnection, $expectModeConnection): bool {
					return $this->countInputConnections($effectiveFlow, 'assistant', 'resume') === ($expectResumeConnection ? 1 : 0)
						&& $this->countInputConnections($effectiveFlow, 'assistant', 'mode') === ($expectModeConnection ? 1 : 0);
				}),
				$context
			)
			->willReturn($flow);

		$compiler = $this->createMock(IAgentFlowCompiler::class);
		$compiler->method('compile')->willReturn(new AgentFlowCompilation(
			$this->minimalCompiledFlow()
		));

		return new AgentExecutionService($contextFactory, $flowFactory, $compiler);
	}

	/** @return array<string,mixed> */
	private function minimalAgentSettings(): array {
		return [
			'chatmodel' => 'chat-main'
		];
	}

	/** @return array<string,mixed> */
	private function minimalCompiledFlow(): array {
		return [
			'nodes' => [[
				'id' => 'assistant',
				'type' => 'aiassistantnode',
				'docks' => [
					'chatmodel' => ['preset_chat_main']
				]
			]],
			'resources' => [[
				'id' => 'preset_chat_main',
				'type' => 'configuredchatmodelagentresource',
				'config' => [
					'service' => [
						'mode' => 'fixed',
						'value' => 'llm-a'
					]
				]
			]],
			'connections' => [[
				'from' => '__input__',
				'output' => 'prompt',
				'to' => 'assistant',
				'input' => 'prompt'
			]]
		];
	}

	/** @param array<string,mixed> $flow */
	private function countInputConnections(array $flow, string $nodeId, string $inputName): int {
		$count = 0;

		foreach (($flow['connections'] ?? []) as $connection) {
			if (!is_array($connection)) {
				continue;
			}
			if (
				(string)($connection['from'] ?? '') === '__input__'
				&& (string)($connection['output'] ?? '') === $inputName
				&& (string)($connection['to'] ?? '') === $nodeId
				&& (string)($connection['input'] ?? '') === $inputName
			) {
				$count++;
			}
		}

		return $count;
	}
}
