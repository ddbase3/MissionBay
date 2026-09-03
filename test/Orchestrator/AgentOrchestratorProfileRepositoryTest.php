<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use MissionBay\Orchestrator\Profile\AgentOrchestratorProfileRepository;
use PHPUnit\Framework\TestCase;

final class AgentOrchestratorProfileRepositoryTest extends TestCase {

	public function testExposesSevenBuiltinProfilesIncludingLargeCatalogVariantsAndNativeToolLoop(): void {
		$repository = new AgentOrchestratorProfileRepository($this->settingsStore());
		$profiles = $repository->getProfiles();

		$this->assertCount(7, $profiles);
		$this->assertArrayHasKey('large-catalog', $profiles);
		$this->assertArrayHasKey('native-tool-loop', $profiles);
		$this->assertArrayHasKey('large-catalog-native', $profiles);

		$profile = $profiles['large-catalog'];
		$config = $profile->getCapabilitySelection();

		$this->assertTrue($profile->isBuiltin());
		$this->assertFalse($profile->isCapabilitySelectionEnabled());
		$this->assertTrue($profile->isAiCapabilitySelectionEnabled());
		$this->assertContains('ai-capability-selection', $profile->getStageIds());
		$this->assertNotContains('capability-selection', $profile->getStageIds());
		$this->assertSame(AgentCapabilitySelectionConfig::STRATEGY_HYBRID, $config->getStrategy());
		$this->assertSame(16, $config->getMaxTools());
		$this->assertSame(12, $config->getSelectAllThreshold());
		$this->assertSame(48, $config->getSemanticCandidateTools());
		$this->assertSame(48000, $config->getSemanticMaxPromptCharacters());
		$this->assertFalse($config->isSticky());
	}

	public function testControlledBuiltinProfilesUseAiGuardedModelDecisionByDefault(): void {
		$repository = new AgentOrchestratorProfileRepository($this->settingsStore());

		foreach ($repository->getProfiles() as $id => $profile) {
			if (in_array($id, ['native-tool-loop', 'large-catalog-native'], true)) {
				continue;
			}
			$this->assertSame(AgentModelDecisionConfig::STRATEGY_AI_GUARDED, $profile->getModelDecision()->getStrategy());
			$this->assertTrue($profile->getModelDecision()->isRepairEnabled());
		}
	}

	public function testNoBuiltinProfileUsesLegacySimpleModelDecision(): void {
		$repository = new AgentOrchestratorProfileRepository($this->settingsStore());

		foreach ($repository->getProfiles() as $profile) {
			$this->assertFalse(
				$profile->getModelDecision()->getStrategy() === AgentModelDecisionConfig::STRATEGY_SIMPLE
			);
		}
	}

	public function testNativeToolLoopProfileUsesLiveNativeSemantics(): void {
		$profile = (new AgentOrchestratorProfileRepository($this->settingsStore()))->getProfile('native-tool-loop');

		$this->assertSame('Native tool loop', $profile->getLabel());
		$this->assertSame(AgentModelDecisionConfig::STRATEGY_NATIVE, $profile->getModelDecision()->getStrategy());
		$this->assertFalse($profile->getModelDecision()->isRepairEnabled());
		$this->assertSame(10, $profile->getMaxToolLoops());
		$this->assertFalse($profile->isCapabilitySelectionEnabled());
		$this->assertFalse($profile->isAiCapabilitySelectionEnabled());
		$this->assertSame([
			'model-decision',
			'action-policy',
			'tool-execution',
			'tool-observation'
		], $profile->getStageIds());
	}


	public function testLargeCatalogNativeUsesSourceCompleteSelectionAndNativeStreaming(): void {
		$profile = (new AgentOrchestratorProfileRepository($this->settingsStore()))->getProfile('large-catalog-native');
		$config = $profile->getCapabilitySelection();

		$this->assertSame('Large catalog native tool loop', $profile->getLabel());
		$this->assertSame(AgentModelDecisionConfig::STRATEGY_NATIVE, $profile->getModelDecision()->getStrategy());
		$this->assertFalse($profile->getModelDecision()->isRepairEnabled());
		$this->assertFalse($profile->isCapabilitySelectionEnabled());
		$this->assertTrue($profile->isAiCapabilitySelectionEnabled());
		$this->assertSame(AgentCapabilitySelectionConfig::SELECTION_UNIT_SOURCE, $config->getSelectionUnit());
		$this->assertSame(16, $config->getMaxTools());
		$this->assertSame(8, $config->getMaxSources());
		$this->assertTrue($config->isSticky());
		$this->assertSame([
			'capability-discovery',
			'ai-capability-selection',
			'model-decision',
			'action-policy',
			'tool-execution',
			'context-compaction',
			'tool-observation'
		], $profile->getStageIds());
	}

	public function testNativeCustomProfileRejectsSemanticVerificationUntilNativeVerificationIsSupported(): void {
		$repository = new AgentOrchestratorProfileRepository($this->settingsStore());

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Native model decision cannot be combined with semantic verification');

		$repository->save('native-verified', [
			'label' => 'Native verified',
			'mode' => 'standard',
			'model_decision' => AgentModelDecisionConfig::native()->toArray(),
			'optional_stages' => [
				'capability-discovery' => true,
				'capability-selection' => true,
				'ai-capability-selection' => false,
				'context-compaction' => true,
				'semantic-verification' => true
			]
		]);
	}

	public function testSourceCompleteSelectionRequiresAiCapabilitySelectionStage(): void {
		$repository = new AgentOrchestratorProfileRepository($this->settingsStore());

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Source-complete capability selection requires the AI capability selection stage.');

		$repository->save('invalid-source-selection', [
			'label' => 'Invalid source selection',
			'mode' => 'standard',
			'optional_stages' => [
				'capability-discovery' => true,
				'capability-selection' => true,
				'ai-capability-selection' => false,
				'context-compaction' => true,
				'semantic-verification' => true
			],
			'capability_selection' => [
				'enabled' => true,
				'selection_unit' => AgentCapabilitySelectionConfig::SELECTION_UNIT_SOURCE
			]
		]);
	}

	public function testLegacySemanticCustomProfileMigratesToExplicitAiStage(): void {
		$repository = new AgentOrchestratorProfileRepository($this->settingsStore([
			AgentOrchestratorProfileRepository::SETTINGS_GROUP => [
				'legacy-semantic' => [
					'label' => 'Legacy semantic',
					'mode' => 'standard',
					'optional_stages' => [
						'capability-discovery' => true,
						'capability-selection' => true,
						'context-compaction' => true,
						'semantic-verification' => true
					],
					'capability_selection' => [
						'strategy' => 'semantic',
						'max_tools' => 16,
						'select_all_threshold' => 12
					]
				]
			]
		]));

		$profile = $repository->getProfile('legacy-semantic');

		$this->assertFalse($profile->isCapabilitySelectionEnabled());
		$this->assertTrue($profile->isAiCapabilitySelectionEnabled());
		$this->assertContains('ai-capability-selection', $profile->getStageIds());
		$this->assertSame(AgentCapabilitySelectionConfig::STRATEGY_HYBRID, $profile->getCapabilitySelection()->getStrategy());
	}

	/** @param array<string,array<string,array<string,mixed>>> $groups */
	private function settingsStore(array $groups = []): ISettingsStore {
		return new class($groups) implements ISettingsStore {
			public function __construct(private array $groups) {}
			public function get(string $group, string $name, array $default = []): array { return $this->groups[$group][$name] ?? $default; }
			public function set(string $group, string $name, array $settings): void { $this->groups[$group][$name] = $settings; }
			public function has(string $group, string $name): bool { return isset($this->groups[$group][$name]); }
			public function remove(string $group, string $name): void { unset($this->groups[$group][$name]); }
			public function getGroup(string $group): array { return $this->groups[$group] ?? []; }
			public function save(): void {}
			public function reload(): void {}
		};
	}
}
