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

namespace Base3\MissionBay\Test\Tool\Profile;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Dto\AgentExecutionRequest;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IAgentComponentPresetMaterializer;
use Base3\MissionBay\Api\IAgentComponentPresetRepository;
use Base3\MissionBay\Capability\AgentCapabilityCatalogBuilder;
use Base3\MissionBay\Dto\AgentComponentPresetMaterialization;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use Base3\MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use Base3\MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use Base3\MissionBay\Profile\AgentToolProfileResolver;
use Base3\MissionBay\Test\Profile\ProfileToolsTestAgentTool;
use Base3\MissionBay\Tool\Profile\MissionBayAgentToolProfileProvider;
use PHPUnit\Framework\TestCase;

final class MissionBayToolProfileProviderBoundaryTest extends TestCase {

	private function provider(array $profiles = [], array $presets = [], array $materializations = [], ?array &$createdContext = null): MissionBayAgentToolProfileProvider {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('getGroup')->willReturnCallback(static fn(string $group): array => $group === 'tool-profile' ? $profiles : []);
		$settings->method('get')->willReturnCallback(static fn(string $group, string $id, mixed $default = null): mixed => $group === 'tool-profile' ? ($profiles[$id] ?? $default) : $default);
		$repository = $this->createStub(IAgentComponentPresetRepository::class);
		$repository->method('getPreset')->willReturnCallback(static fn(string $id, array $default = []): array => $presets[$id] ?? $default);
		$repository->method('getPresets')->willReturn($presets);
		$context = $this->createStub(IAgentContext::class);
		$materializer = $this->createStub(IAgentComponentPresetMaterializer::class);
		$materializer->method('createContext')->willReturnCallback(static function(array $vars) use ($context, &$createdContext): IAgentContext {
			$createdContext = $vars;
			return $context;
		});
		$materializer->method('materialize')->willReturnCallback(static fn(string $id, IAgentContext $context): AgentComponentPresetMaterialization => $materializations[$id]);
		$semantics = new AgentToolDefinitionSemantics();
		$fingerprint = new AgentActionFingerprint();
		return new MissionBayAgentToolProfileProvider(
			new AgentToolProfileResolver($settings, $repository),
			$materializer,
			new AgentCapabilityCatalogBuilder(),
			$semantics,
			new AgentToolContractValidationService(),
			$fingerprint,
			new AgentMutationCommitGuardService($fingerprint, null, $semantics)
		);
	}

	private function request(): AgentExecutionRequest {
		return new AgentExecutionRequest(['name' => 'example'], ['question' => 'hello'], ['runtime_id' => 'test-runtime']);
	}

	private function profile(array $presets = ['search']): array {
		return ['enabled' => true, 'internal_enabled' => true, 'tools' => $presets, 'label' => 'Profile'];
	}

	private function preset(array $capabilities = ['tool']): array {
		return ['enabled' => true, 'capabilities' => $capabilities];
	}

	private function materialization(?ProfileToolsTestAgentTool $tool = null, array $warnings = []): AgentComponentPresetMaterialization {
		return new AgentComponentPresetMaterialization('search', [], null, $tool, null, null, ['tool'], $warnings);
	}

	private function definition(string $name, array $annotations): array {
		return [
			'type' => 'function',
			'function' => [
				'name' => $name,
				'description' => 'Read a resource',
				'parameters' => ['type' => 'object', 'properties' => [], 'additionalProperties' => false]
			],
			'annotations' => $annotations
		];
	}

	public function testProviderNameAndIdentityAreStable(): void {
		$this->assertSame('missionbayagenttoolprofileprovider', MissionBayAgentToolProfileProvider::getName());
		$this->assertSame('missionbay', MissionBayAgentToolProfileProvider::getProviderId());
	}

	public function testUnknownProfileIsAbsent(): void {
		$this->assertFalse($this->provider()->hasProfile('no-such-profile'));
	}

	public function testWhitespaceOnlyProfileIdIsAbsent(): void {
		$this->assertFalse($this->provider()->hasProfile(' !!! '));
	}

	public function testProfileIdsAreNormalizedBeforeLookup(): void {
		$this->assertTrue($this->provider(['profile-one' => $this->profile()])->hasProfile(' PROFILE-ONE '));
	}

	public function testInactiveProfileIsNotExposedInCatalog(): void {
		$profiles = ['hidden' => ['label' => 'Hidden', 'enabled' => false, 'tools' => ['search']]];
		$this->assertSame([], $this->provider($profiles)->getOptions());
		$this->assertFalse($this->provider($profiles)->hasProfile('hidden'));
	}

	public function testInternalDisabledProfileIsNotExposed(): void {
		$profiles = ['hidden' => ['enabled' => true, 'internal_enabled' => false]];
		$this->assertFalse($this->provider($profiles)->hasProfile('hidden'));
	}

	public function testProfilesAreSortedByLabel(): void {
		$profiles = [
			'second' => ['enabled' => true, 'label' => 'Zulu', 'tools' => []],
			'first' => ['enabled' => true, 'label' => 'Alpha', 'tools' => []]
		];
		$this->assertSame(['first', 'second'], array_column($this->provider($profiles)->getOptions(), 'id'));
	}

	public function testNoProfilesProduceEmptyGovernedCatalog(): void {
		$provider = $this->provider();
		$set = $provider->resolve([], $this->request());
		$this->assertSame([], $set->getCatalog()->all());
		$this->assertSame([], $set->getWarnings());
	}

	public function testNoProfilesStillBindIsolatedRuntimeContext(): void {
		$vars = null;
		$this->provider(createdContext: $vars)->resolve([], $this->request());
		$this->assertSame('test-runtime', $vars['runtime_id']);
		$this->assertSame('governed', $vars['tool_mode']);
		$this->assertSame(['name' => 'example'], $vars['agent_configuration']);
		$this->assertSame(['question' => 'hello'], $vars['agent_inputs']);
		$this->assertSame([], $vars['tool_profiles']);
	}

	public function testProfileIdsAreDeduplicatedBeforeRuntimeContext(): void {
		$vars = null;
		$this->provider(['my-profile' => $this->profile([])], createdContext: $vars)->resolve(['', ' !!! ', 'My-Profile', 'my-profile'], $this->request());
		$this->assertSame(['my-profile'], $vars['tool_profiles']);
	}

	public function testMissingProfilePreventsMaterialization(): void {
		$this->expectException(\RuntimeException::class);
		$this->provider()->resolve(['missing'], $this->request());
	}

	public function testDisabledProfileCannotBeResolved(): void {
		$this->expectException(\RuntimeException::class);
		$this->provider(['search-profile' => ['enabled' => false]])->resolve(['search-profile'], $this->request());
	}

	public function testProfileUnavailableToInternalAgentsCannotBeResolved(): void {
		$this->expectException(\RuntimeException::class);
		$this->provider(['search-profile' => ['enabled' => true, 'internal_enabled' => false]])->resolve(['search-profile'], $this->request());
	}

	public function testUnknownPresetCannotBeMaterialized(): void {
		$this->expectException(\RuntimeException::class);
		$this->provider(['search-profile' => $this->profile()])->resolve(['search-profile'], $this->request());
	}

	public function testDisabledPresetCannotBeMaterialized(): void {
		$this->expectException(\RuntimeException::class);
		$this->provider(['search-profile' => $this->profile()], ['search' => ['enabled' => false]])->resolve(['search-profile'], $this->request());
	}

	public function testPresetWithoutToolCapabilityCannotBeMaterialized(): void {
		$this->expectException(\RuntimeException::class);
		$this->provider(['search-profile' => $this->profile()], ['search' => $this->preset(['memory'])])->resolve(['search-profile'], $this->request());
	}

	public function testPresetWithNoToolRecordsWarningInsteadOfInventingTool(): void {
		$set = $this->provider(['p' => $this->profile()], ['search' => $this->preset()], ['search' => $this->materialization()])->resolve(['p'], $this->request());
		$this->assertSame([], $set->getCatalog()->all());
		$this->assertStringContainsString('no executable tool', implode('; ', $set->getWarnings()));
	}

	public function testMaterializationWarningsArePreserved(): void {
		$set = $this->provider(['p' => $this->profile()], ['search' => $this->preset()], ['search' => $this->materialization(null, ['warning-one'])])->resolve(['p'], $this->request());
		$this->assertTrue(in_array('warning-one', $set->getWarnings(), true));
	}

	public function testToolWithoutExplicitSideEffectSemanticsIsNotExposed(): void {
		$tool = new ProfileToolsTestAgentTool([$this->definition('lookup', [])]);
		$set = $this->provider(['p' => $this->profile()], ['search' => $this->preset()], ['search' => $this->materialization($tool)])->resolve(['p'], $this->request());
		$this->assertSame([], $set->getCatalog()->all());
		$this->assertStringContainsString('not exposed', implode('; ', $set->getWarnings()));
	}

	public function testExplicitReadOnlyToolAppearsInCatalog(): void {
		$tool = new ProfileToolsTestAgentTool([$this->definition('lookup', ['readOnlyHint' => true])]);
		$set = $this->provider(['p' => $this->profile()], ['search' => $this->preset()], ['search' => $this->materialization($tool)])->resolve(['p'], $this->request());
		$this->assertSame(['lookup'], array_map(static fn($capability): string => $capability->getName(), $set->getCatalog()->all()));
		$this->assertSame([], $set->getWarnings());
	}

	public function testMutationWithoutGuardedImplementationIsNotExposed(): void {
		$tool = new ProfileToolsTestAgentTool([$this->definition('change', ['mutation' => true, 'requiresApproval' => true])]);
		$set = $this->provider(['p' => $this->profile()], ['search' => $this->preset()], ['search' => $this->materialization($tool)])->resolve(['p'], $this->request());
		$this->assertSame([], $set->getCatalog()->all());
		$this->assertStringContainsString('commit guard', implode('; ', $set->getWarnings()));
	}

	public function testMutationWithoutApprovalIsNotExposed(): void {
		$tool = new ProfileToolsTestAgentTool([$this->definition('change', ['readOnlyHint' => false, 'requiresApproval' => false, 'commitGuardRequired' => false])]);
		$set = $this->provider(['p' => $this->profile()], ['search' => $this->preset()], ['search' => $this->materialization($tool)])->resolve(['p'], $this->request());
		$this->assertSame([], $set->getCatalog()->all());
		$this->assertStringContainsString('does not require approval', implode('; ', $set->getWarnings()));
	}
}
