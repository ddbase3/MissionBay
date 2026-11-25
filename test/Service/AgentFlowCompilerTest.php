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

use Base3\AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use Base3\AssistantFoundation\Dto\AgentCapabilitySourceConfig;
use Base3\MissionBay\Api\IAgentComponentFlowBuilder;
use Base3\MissionBay\Service\AgentFlowCompiler;
use Base3\MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use Base3\MissionBay\Test\Orchestrator\NativeProfileRepositoryTestSupport;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Orchestrator/NativeProfileRepositoryTestSupport.php';

final class AgentFlowCompilerTest extends TestCase {

	use NativeProfileRepositoryTestSupport;

	public function testMissingChatModelIsRejectedBeforeBuildingFlow(): void {
		$builder = $this->createMock(IAgentComponentFlowBuilder::class);
		$builder->expects($this->never())->method('build');
		$this->expectException(\RuntimeException::class);
		(new AgentFlowCompiler($builder, $this->nativeRepository()))->compile(['chatmodel' => ' ? ']);
	}

	public function testBaseFlowAndChatModelAreNormalized(): void {
		$receivedFlow = [];
		$receivedComponents = [];
		$builder = $this->builder($receivedFlow, $receivedComponents);
		$result = (new AgentFlowCompiler($builder, $this->nativeRepository()))->compile(['chatmodel' => ' MODEL / A-1 ']);
		$this->assertSame('agentflowcompiler', AgentFlowCompiler::getName());
		$this->assertSame('assistant', $receivedFlow['nodes'][0]['id']);
		$this->assertSame('aiassistantnode', $receivedFlow['nodes'][0]['type']);
		$this->assertCount(2, $receivedFlow['connections']);
		$this->assertSame('modela-1', $receivedComponents[0]['preset']);
		$this->assertSame(['chatmodel'], $receivedComponents[0]['attach_as']);
		$this->assertSame('assistant', $result->getFlow()['nodes'][0]['id']);
		$this->assertSame([], $result->getWarnings());
		$this->assertSame('native-tool-loop', $receivedFlow['nodes'][0]['inputs']['orchestratorprofile']);
		$this->assertSame(AgentModelDecisionConfig::native()->toArray(), $receivedFlow['nodes'][0]['inputs']['modeldecision']);
		$this->assertSame(['model-decision', 'action-policy', 'tool-execution', 'tool-observation'], $receivedFlow['nodes'][0]['inputs']['stages']);
	}

	public function testDirectComponentsOverrideAndMergeChatModelDockAssignments(): void {
		$flow = [];
		$components = [];
		$compiler = new AgentFlowCompiler($this->builder($flow, $components), $this->nativeRepository());
		$compiler->compile([
			'chatmodel' => 'model',
			'agent_components' => [
				'model' => ['attach_as' => ['tool'], 'tool_namespace' => 'local'],
				['preset' => 'model', 'attach_as' => ['memory'], 'enabled' => false],
				['preset' => 'other', 'attach_as' => ['context']],
				['attach_as' => ['tool']],
				false
			]
		]);
		$this->assertCount(2, $components);
		$this->assertSame(['chatmodel', 'tool', 'memory'], $components[0]['attach_as']);
		$this->assertSame(false, $components[0]['enabled']);
		$this->assertSame('local', $components[0]['tool_namespace']);
		$this->assertSame('other', $components[1]['preset']);
	}

	public function testBuilderWarningsAreReturnedWithoutDuplicates(): void {
		$flow = [];
		$components = [];
		$builder = $this->builder($flow, $components, ['not expanded', 'not expanded', 'unknown dock']);
		$result = (new AgentFlowCompiler($builder, $this->nativeRepository()))->compile(['chatmodel' => 'model']);
		$this->assertSame(['not expanded', 'unknown dock'], $result->getWarnings());
	}

	public function testLegacyCapabilityOverridesAreNormalized(): void {
		$flow = [];
		$components = [];
		$settings = [
			'chatmodel' => 'model',
			'capability_sources' => ['tools' => ['read']],
			'capability_selection' => ['enabled' => true]
		];
		(new AgentFlowCompiler($this->builder($flow, $components), $this->nativeRepository()))->compile($settings);
		$this->assertSame(AgentCapabilitySourceConfig::fromArray($settings['capability_sources'])->toArray(), $flow['nodes'][0]['inputs']['capabilitysources']);
		$this->assertSame(AgentCapabilitySelectionConfig::fromArray($settings['capability_selection'])->toArray(), $flow['nodes'][0]['inputs']['capabilityselection']);
		$this->assertSame('native-tool-loop', $flow['nodes'][0]['inputs']['orchestratorprofile']);
		$this->assertSame(['capability-selection', 'model-decision', 'action-policy', 'tool-execution', 'tool-observation'], $flow['nodes'][0]['inputs']['stages']);
	}

	public function testExplicitOrchestratorProfileSettingSuppressesLegacyOverridesWithoutExpertMode(): void {
		$flow = [];
		$components = [];
		(new AgentFlowCompiler($this->builder($flow, $components), $this->nativeRepository()))->compile([
			'chatmodel' => 'model',
			'orchestrator_profile' => '',
			'capability_sources' => ['tools' => ['read']],
			'capability_selection' => ['enabled' => true]
		]);
		$this->assertArrayNotHasKey('capabilitysources', $flow['nodes'][0]['inputs']);
		$this->assertSame('native-tool-loop', $flow['nodes'][0]['inputs']['orchestratorprofile']);
		$this->assertFalse($flow['nodes'][0]['inputs']['capabilityselection']['enabled']);
		$this->assertNotContains('capability-selection', $flow['nodes'][0]['inputs']['stages']);
	}

	public function testExpertOverridesCanBeEnabledByStringFlag(): void {
		$flow = [];
		$components = [];
		(new AgentFlowCompiler($this->builder($flow, $components), $this->nativeRepository()))->compile([
			'chatmodel' => 'model',
			'orchestrator_profile' => '',
			'expert_overrides_enabled' => ' YES ',
			'capability_sources' => [],
			'capability_selection' => 'invalid'
		]);
		$this->assertArrayHasKey('capabilitysources', $flow['nodes'][0]['inputs']);
		$this->assertArrayHasKey('capabilityselection', $flow['nodes'][0]['inputs']);
	}

	public function testUnresolvableProfilesAreRejectedWithoutInventingDefaults(): void {
		$compiler = new AgentFlowCompiler($this->createStub(IAgentComponentFlowBuilder::class), $this->nativeRepository());
		foreach (['orchestrator_profile' => 'managed', 'tool_profiles' => "tool-a,\ntool-b", 'memory_profile' => 'work', 'context_profile' => 'history'] as $key => $value) {
			try {
				$compiler->compile(['chatmodel' => 'model', $key => $value]);
				$this->fail('Expected missing resolver for ' . $key);
			} catch (\RuntimeException $e) {
				$this->assertStringContainsString($key === 'orchestrator_profile' ? 'not found' : 'not available', $e->getMessage());
			}
		}
	}

	public function testDirectCallDisablesToolConfigurationWithoutDroppingOtherRoles(): void {
		$flow = [];
		$components = [];
		(new AgentFlowCompiler($this->builder($flow, $components), $this->nativeRepository()))->compile([
			'chatmodel' => 'model', 'orchestrator_profile' => 'direct-call',
			'tool_profiles' => ['missing-profile'], 'expert_overrides_enabled' => true,
			'capability_sources' => ['tools' => ['read']], 'capability_selection' => ['enabled' => true],
			'agent_components' => [
				['preset' => 'memory', 'attach_as' => ['memory']],
				['preset' => 'context', 'attach_as' => ['context']]
			]
		]);
		$inputs = $flow['nodes'][0]['inputs'];
		$this->assertSame(0, $inputs['maxtoolloops']);
		$this->assertSame([], $inputs['stages']);
		$this->assertArrayNotHasKey('capabilitysources', $inputs);
		$this->assertFalse($inputs['capabilityselection']['enabled']);
		$this->assertSame(['model', 'memory', 'context'], array_column($components, 'preset'));
	}

	public function testBuilderCannotOmitAssistantNode(): void {
		$builder = $this->createStub(IAgentComponentFlowBuilder::class);
		$builder->method('build')->willReturn(['nodes' => [], 'resources' => [], 'connections' => []]);
		$this->expectException(\RuntimeException::class);
		(new AgentFlowCompiler($builder, $this->nativeRepository()))->compile(['chatmodel' => 'model']);
	}

	public function testCompiledFlowRequiresExactlyOneChatModelDock(): void {
		foreach ([[], ['one', 'two'], [null, '']] as $dock) {
			$builder = $this->createStub(IAgentComponentFlowBuilder::class);
			$builder->method('build')->willReturn(['nodes' => [['id' => 'assistant', 'docks' => ['chatmodel' => $dock]]]]);
			try {
				(new AgentFlowCompiler($builder, $this->nativeRepository()))->compile(['chatmodel' => 'model']);
				$this->fail('Expected model dock cardinality validation.');
			} catch (\RuntimeException $e) {
				$this->assertStringContainsString('exactly once', $e->getMessage());
			}
		}
	}

	private function builder(array &$flow, array &$components, array $warnings = []): IAgentComponentFlowBuilder {
		$builder = $this->createStub(IAgentComponentFlowBuilder::class);
		$builder->method('build')->willReturnCallback(static function(array $base, array $parts, string $nodeId) use (&$flow, &$components): array {
			$flow = $base;
			$components = $parts;
			$base['nodes'][0]['docks']['chatmodel'] = ['resolved-model'];
			return $base;
		});
		$builder->method('getWarnings')->willReturn($warnings);
		return $builder;
	}
}
