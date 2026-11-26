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

namespace Base3\MissionBay\Test\Service;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Dto\AgentExecutionRequest;
use Base3\MissionBay\Api\IAgentComponentPresetRepository;
use Base3\MissionBay\Api\IAgentContextFactory;
use Base3\MissionBay\Api\IAgentFlow;
use Base3\MissionBay\Api\IAgentFlowCompiler;
use Base3\MissionBay\Api\IAgentFlowFactory;
use Base3\MissionBay\Dto\AgentFlowCompilation;
use Base3\MissionBay\Service\AgentComponentFlowBuilder;
use Base3\MissionBay\Service\AgentComponentPresetFlowExpander;
use Base3\MissionBay\Service\AgentExecutionService;
use Base3\MissionBay\Service\AgentFlowCompiler;
use Base3\MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use Base3\MissionBay\Test\Orchestrator\NativeProfileRepositoryTestSupport;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Orchestrator/NativeProfileRepositoryTestSupport.php';

final class AgentExecutionServiceCapabilityConfigTest extends TestCase {

	use NativeProfileRepositoryTestSupport;

	public function testAutoUsesEffectiveComponentDocksAndEnabledSources(): void {
		$presets = $this->componentPresetRepository();
		$presets->savePreset('demo-tool', ['type' => 'currenttimeagenttool', 'enabled' => true, 'capabilities' => ['tool']]);
		$presets->savePreset('demo-memory', ['type' => 'sessionmemoryagentresource', 'enabled' => true, 'capabilities' => ['memory']]);
		$presets->savePreset('demo-context', ['type' => 'statictextcontextagentresource', 'enabled' => true, 'capabilities' => ['context']]);
		$store = new \Base3\MissionBay\Test\Orchestrator\AgentOrchestratorProfileRepositoryTestDouble1([
			'tool-profile' => ['demo' => ['enabled' => true, 'internal_enabled' => true, 'tools' => ['demo-tool']]]
		]);
		$compiler = new AgentFlowCompiler(new AgentComponentFlowBuilder($presets, new AgentComponentPresetFlowExpander($presets)), $this->nativeRepository($store), new \Base3\MissionBay\Profile\AgentToolProfileResolver($store, $presets));
		$base = ['chatmodel' => 'chat-main', 'orchestrator_profile' => 'auto'];
		foreach ([
			[[], 'direct-call'],
			[['agent_components' => [['preset' => 'demo-tool', 'attach_as' => ['tool'], 'enabled' => false]]], 'direct-call'],
			[['agent_components' => [['preset' => 'demo-memory', 'attach_as' => ['memory']], ['preset' => 'demo-context', 'attach_as' => ['context']]]], 'direct-call'],
			[['tool_profiles' => ['demo']], 'program-tool-loop'],
			[['agent_components' => [['preset' => 'demo-tool', 'attach_as' => ['tool']]]], 'program-tool-loop'],
			[['capability_sources' => ['tools' => ['read']]], 'direct-call'],
			[['expert_overrides_enabled' => true, 'capability_sources' => ['providers' => ['source']]], 'program-tool-loop'],
			[['expert_overrides_enabled' => true, 'capability_sources' => ['modules' => ['module']]], 'program-tool-loop'],
			[['expert_overrides_enabled' => true, 'capability_sources' => ['resourceProviders' => ['resource']]], 'program-tool-loop'],
			[['expert_overrides_enabled' => true, 'capability_sources' => ['promptProviders' => ['prompt']]], 'program-tool-loop']
		] as [$settings, $expected]) {
			$node = $compiler->compile(array_replace($base, $settings))->getFlow()['nodes'][0];
			$this->assertSame($expected, $node['inputs']['orchestratorprofile']);
			$this->assertSame($expected === 'direct-call' ? 0 : 32, $node['inputs']['maxtoolloops']);
		}
		$this->expectException(\RuntimeException::class);
		$compiler->compile($base + ['tool_profiles' => ['missing']]);
	}

	public function testCompilerBuildsCanonicalChatModelPresetFlow(): void {
		$flow = $this->createCompiler()->compile([
			'chatmodel' => 'chat-main'
		])->getFlow();

		$inputs = $flow['nodes'][0]['inputs'];
		$this->assertSame('native-tool-loop', $inputs['orchestratorprofile']);
		$this->assertSame(AgentModelDecisionConfig::native()->toArray(), $inputs['modeldecision']);
		$this->assertSame(['model-decision', 'action-policy', 'tool-execution', 'tool-observation'], $inputs['stages']);
		$this->assertSame(10, $inputs['maxtoolloops']);
		$this->assertFalse($inputs['capabilityselection']['enabled']);
		$this->assertFalse($inputs['deliberateplanning']);
		$this->assertSame([[
			'id' => 'assistant',
			'type' => 'aiassistantnode',
			'docks' => [
				'chatmodel' => ['preset_chat_main']
			],
			'inputs' => $inputs
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
		$repository = $this->componentPresetRepository();
		return new AgentFlowCompiler(new AgentComponentFlowBuilder($repository, new AgentComponentPresetFlowExpander($repository)), $this->nativeRepository());
	}

	private function componentPresetRepository(): IAgentComponentPresetRepository {
		return new AgentExecutionServiceCapabilityConfigTestDouble1();
	}

	private function createExecutionService(bool $expectResumeConnection, bool $expectModeConnection = false): AgentExecutionService {
		$context = $this->createStub(IAgentContext::class);
		$contextFactory = $this->createStub(IAgentContextFactory::class);
		$contextFactory->method('createContext')->willReturn($context);

		$flow = $this->createStub(IAgentFlow::class);
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

		$compiler = $this->createStub(IAgentFlowCompiler::class);
		$compiler->method('compile')->willReturn(new AgentFlowCompilation(
			$this->minimalCompiledFlow()
		));

		return new AgentExecutionService($contextFactory, $flowFactory, $compiler, new \Base3\MissionBay\Orchestrator\Service\AgentActionResumeService(
			new \Base3\MissionBay\Orchestrator\AgentActionFingerprint(),
			$this->createStub(\Base3\AssistantFoundation\Api\IAgentSuspensionRepository::class)
		));
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

require_once __DIR__ . '/AgentExecutionServiceCapabilityConfigTestDouble1.php';

require_once dirname(__DIR__) . '/Orchestrator/AgentOrchestratorProfileRepositoryTestDouble1.php';
