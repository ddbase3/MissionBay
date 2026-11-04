<?php declare(strict_types=1);

namespace MissionBay\Test\Composition;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use Base3\Api\IComponentResolver;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentFlowCompiler;
use MissionBay\Api\IAgentFlowFactory;
use MissionBay\Api\IAgentNodeFactory;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentMemoryRoleResolver;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Capability\AgentCapabilityCatalogBuilder;
use MissionBay\Capability\AgentCapabilityDiscoveryService;
use MissionBay\Composition\AgentCompositionInspector;
use MissionBay\Dto\AgentFlowCompilation;
use MissionBay\Flow\StrictFlow;
use MissionBay\Orchestrator\AgentStagePipelineResolver;
use MissionBay\Orchestrator\Profile\AgentOrchestratorProfileRepository;
use MissionBay\Profile\AgentContextProfileResolver;
use MissionBay\Profile\AgentMemoryProfileResolver;
use MissionBay\Profile\AgentToolProfileResolver;
use PHPUnit\Framework\TestCase;

final class AgentCompositionInspectorBehaviorTest extends TestCase {

	private function inspector(array $flow = [], array $warnings = [], array $groups = [], array $presets = [], bool $runtime = false): AgentCompositionInspector {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('getGroup')->willReturnCallback(static fn(string $group): array => $groups[$group] ?? []);
		$settings->method('get')->willReturnCallback(static fn(string $group, string $id, mixed $default = null): mixed => $groups[$group][$id] ?? $default);

		$repository = $this->createStub(IAgentComponentPresetRepository::class);
		$repository->method('getPresets')->willReturn($presets);
		$repository->method('getPreset')->willReturnCallback(static fn(string $id, array $default = []): array => $presets[$id] ?? $default);

		$compiler = $this->createStub(IAgentFlowCompiler::class);
		$compiler->method('compile')->willReturn(new AgentFlowCompilation($flow, $warnings));
		$resourceFactory = $this->createStub(IAgentResourceFactory::class);
		$componentResolver = $this->createStub(IComponentResolver::class);
		$contextFactory = $this->createStub(IAgentContextFactory::class);
		$contextFactory->method('createContext')->willReturn($this->createStub(IAgentContext::class));
		$flowFactory = $this->createStub(IAgentFlowFactory::class);
		if ($runtime) {
			$flowFactory->method('createFromArray')->willReturn(new StrictFlow(
				$this->createStub(IAgentNodeFactory::class),
				$this->createStub(IAgentResourceFactory::class),
				$this->createStub(IAgentConfigValueResolver::class)
			));
			$stages = [];
			foreach (['model-decision', 'action-policy', 'tool-execution', 'tool-observation'] as $stageId) {
				$stage = $this->createStub(IAgentStage::class);
				$stage->method('id')->willReturn($stageId);
				$stage->method('name')->willReturn($stageId);
				$stages[$stageId] = $stage;
			}
			$componentResolver->method('get')->willReturnCallback(static fn(string $interface, string $id): mixed => $stages[$id] ?? null);
		}

		return new AgentCompositionInspector(
			$settings,
			$compiler,
			$contextFactory,
			$flowFactory,
			new AgentOrchestratorProfileRepository($settings),
			new AgentToolProfileResolver($settings, $repository),
			new AgentMemoryProfileResolver($settings, $repository, $resourceFactory),
			new AgentContextProfileResolver($settings, $repository, $resourceFactory),
			$repository,
			new AgentCapabilityDiscoveryService($componentResolver),
			new AgentCapabilityCatalogBuilder(),
			new AgentStagePipelineResolver($componentResolver, ['model-decision', 'action-policy', 'tool-execution', 'tool-observation']),
			$this->createStub(IAgentMemoryRoleResolver::class)
		);
	}

	public function testEmptyConfigurationReturnsValidReadOnlyDiagnostic(): void {
		$report = $this->inspector()->inspect('agent-one', []);
		$this->assertSame('agent-one', $report['agent_id']);
		$this->assertSame('agent-one', $report['label']);
		$this->assertTrue($report['enabled']);
		$this->assertSame('valid', $report['status']);
		$this->assertSame([], $report['errors']);
		$this->assertSame([], $report['tools']);
		$this->assertSame([], $report['memories']);
		$this->assertSame(0, $report['resource_count']);
	}

	public function testLabelAndDisableFlagArePreserved(): void {
		$report = $this->inspector()->inspect('agent-one', ['label' => ' Search ', 'enabled' => 'off', 'chatmodel' => ' my-model ']);
		$this->assertSame('Search', $report['label']);
		$this->assertFalse($report['enabled']);
		$this->assertSame('my-model', $report['chatmodel']);
	}

	public function testWarningOnlyDiagnosticHasWarningStatus(): void {
		$report = $this->inspector([], ['warning-one', ' warning-one ', '', 'warning-two'])->inspect('a', []);
		$this->assertSame('warning', $report['status']);
		$this->assertSame(['warning-one', 'warning-two'], $report['warnings']);
		$this->assertSame([], $report['errors']);
	}

	public function testMissingOrchestratorProfileIsReportedInsteadOfSilentlySelected(): void {
		$report = $this->inspector()->inspect('a', ['orchestrator_profile' => 'missing']);
		$this->assertSame('error', $report['status']);
		$this->assertStringContainsString('Orchestrator profile not found: missing', implode('; ', $report['errors']));
		$this->assertSame('missing', $report['orchestrator']['id']);
	}

	public function testMissingToolProfilesAppearExplicitly(): void {
		$report = $this->inspector()->inspect('a', ['tool_profiles' => ['MISSING', ' missing ']]);
		$this->assertSame('error', $report['status']);
		$this->assertCount(1, $report['tool_profiles']);
		$this->assertSame('missing', $report['tool_profiles'][0]['id']);
		$this->assertSame('missing', $report['tool_profiles'][0]['status']);
		$this->assertStringContainsString('Tool profile not found: missing', implode('; ', $report['errors']));
	}

	public function testDisabledToolProfileCannotBePresentedAsActive(): void {
		$groups = ['tool-profile' => ['private' => ['enabled' => false, 'tools' => ['tool-one']]]];
		$report = $this->inspector(groups: $groups)->inspect('a', ['tool_profiles' => ['private']]);
		$this->assertSame('inactive', $report['tool_profiles'][0]['status']);
		$this->assertSame('error', $report['status']);
		$this->assertStringContainsString('not active', implode('; ', $report['errors']));
	}

	public function testInactiveInternalToolProfileCannotBePresentedAsActive(): void {
		$groups = ['tool-profile' => ['private' => ['enabled' => true, 'internal_enabled' => false]]];
		$report = $this->inspector(groups: $groups)->inspect('a', ['tool_profiles' => ['private']]);
		$this->assertFalse($report['tool_profiles'][0]['internal_enabled']);
		$this->assertSame('inactive', $report['tool_profiles'][0]['status']);
	}

	public function testMissingMemoryProfileIsNotSilentlyUsed(): void {
		$report = $this->inspector()->inspect('a', ['memory_profile' => 'missing']);
		$this->assertSame('missing', $report['memory_profile']['status']);
		$this->assertSame('error', $report['status']);
		$this->assertStringContainsString('Memory profile not found', implode('; ', $report['errors']));
	}

	public function testMissingContextProfileIsNotSilentlyUsed(): void {
		$report = $this->inspector()->inspect('a', ['context_profile' => 'missing']);
		$this->assertSame('missing', $report['context_profile']['status']);
		$this->assertSame('error', $report['status']);
		$this->assertStringContainsString('Context profile not found', implode('; ', $report['errors']));
	}

	public function testNoMemoryAndContextProfilesHaveExplicitNoneStatus(): void {
		$report = $this->inspector()->inspect('a', []);
		$this->assertSame('none', $report['memory_profile']['status']);
		$this->assertSame('none', $report['context_profile']['status']);
	}

	public function testInvalidLegacyComponentsAreNotExposed(): void {
		$report = $this->inspector()->inspect('a', ['agent_components' => [['preset' => ''], ['preset' => 'hidden', 'enabled' => false], 'invalid']]);
		$this->assertSame([], $report['components']);
	}

	public function testMissingLegacyComponentPresetIsExplicitError(): void {
		$report = $this->inspector()->inspect('a', ['agent_components' => [['preset' => 'custom-preset', 'attach_as' => ['tool']]]]);
		$this->assertSame('error', $report['status']);
		$this->assertSame('custom-preset', $report['components'][0]['preset_id']);
		$this->assertStringContainsString('Component preset not found', implode('; ', $report['errors']));
	}

	public function testExistingLegacyPresetRetainsSourceAndRole(): void {
		$presets = ['known' => ['label' => 'Known', 'enabled' => true, 'type' => 'tool', 'config' => ['model' => 'test']]];
		$report = $this->inspector(presets: $presets)->inspect('a', ['agent_components' => [['preset' => 'known', 'attach_as' => ['tool']]]]);
		$this->assertSame('valid', $report['status']);
		$this->assertSame(['tool'], $report['components'][0]['roles']);
		$this->assertSame(['expert/legacy'], $report['components'][0]['sources']);
		$this->assertSame('Known', $report['components'][0]['label']);
	}

	public function testDuplicatedComponentRolesAreMerged(): void {
		$presets = ['known' => ['label' => 'Known']];
		$components = [['preset' => 'known', 'attach_as' => ['tool']], ['preset' => 'known', 'attach_as' => ['context', 'tool']]];
		$report = $this->inspector(presets: $presets)->inspect('a', ['agent_components' => $components]);
		$this->assertCount(1, $report['components']);
		$this->assertSame(['tool', 'context'], $report['components'][0]['roles']);
	}

	public function testPresetConfigurationSecretsAreRedactedRecursively(): void {
		$presets = ['known' => ['config' => ['api_key' => 'secret-value', 'nested' => ['authorization' => 'bearer x', 'label' => 'safe']]]];
		$report = $this->inspector(presets: $presets)->inspect('a', ['agent_components' => [['preset' => 'known']]]);
		$this->assertSame('[redacted]', $report['components'][0]['config']['api_key']);
		$this->assertSame('[redacted]', $report['components'][0]['config']['nested']['authorization']);
		$this->assertSame('safe', $report['components'][0]['config']['nested']['label']);
	}

	public function testEffectiveFlowSecretsAreRedactedWithoutChangingOtherFields(): void {
		$flow = ['nodes' => [['id' => 'assistant', 'inputs' => ['token' => 'private', 'nested' => ['password' => 's3cr3t', 'note' => 'public']]]]];
		$report = $this->inspector(flow: $flow)->inspect('a', []);
		$this->assertSame('[redacted]', $report['effective_flow']['nodes'][0]['inputs']['token']);
		$this->assertSame('[redacted]', $report['effective_flow']['nodes'][0]['inputs']['nested']['password']);
		$this->assertSame('public', $report['effective_flow']['nodes'][0]['inputs']['nested']['note']);
	}

	public function testCapabilitySelectionCanBeReadFromSettings(): void {
		$selection = ['strategy' => 'static'];
		$report = $this->inspector()->inspect('a', ['capability_selection' => $selection]);
		$this->assertSame($selection, $report['capability_selection']);
	}

	public function testExpertConfigurationSourceIsNotReportedAsInstalledPreset(): void {
		$report = $this->inspector()->inspect('a', ['agent_components' => [['preset' => '  ', 'enabled' => true]]]);
		$this->assertSame([], $report['components']);
	}

	public function testRuntimeCompositionInspectsEmptyResourceFlowWithoutExecutingNodes(): void {
		$flow = ['nodes' => [['id' => 'assistant', 'inputs' => ['stages' => ['model-decision', 'action-policy', 'tool-execution', 'tool-observation']]]]];
		$report = $this->inspector(flow: $flow, runtime: true)->inspect('agent-one', []);
		$this->assertSame('valid', $report['status']);
		$this->assertSame(0, $report['resource_count']);
		$this->assertSame([], $report['tools']);
		$this->assertSame([], $report['memories']);
		$this->assertSame(['model-decision', 'action-policy', 'tool-execution', 'tool-observation'], $report['final_stage_ids']);
	}

	public function testStrictDiscoveryReportsMissingConfiguredToolsAsErrors(): void {
		$flow = ['nodes' => [['id' => 'assistant', 'inputs' => ['stages' => ['model-decision', 'action-policy', 'tool-execution', 'tool-observation']]]]];
		$report = $this->inspector(flow: $flow, runtime: true)->inspect('agent-one', ['capability_sources' => ['tools' => ['unknown-tool'], 'strict' => true]]);
		$this->assertSame('error', $report['status']);
		$this->assertStringContainsString('not found', implode('; ', $report['errors']));
	}

	public function testPermissiveDiscoveryReportsMissingConfiguredToolsAsWarnings(): void {
		$flow = ['nodes' => [['id' => 'assistant', 'inputs' => ['stages' => ['model-decision', 'action-policy', 'tool-execution', 'tool-observation']]]]];
		$report = $this->inspector(flow: $flow, runtime: true)->inspect('agent-one', ['capability_sources' => ['tools' => ['unknown-tool'], 'strict' => false]]);
		$this->assertSame('warning', $report['status']);
		$this->assertStringContainsString('not found', implode('; ', $report['warnings']));
		$this->assertSame([], $report['errors']);
	}
}
