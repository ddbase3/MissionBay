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

namespace Base3\MissionBay\Test\Orchestrator;

use Base3\AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use Base3\MissionBay\Orchestrator\Profile\AgentOrchestratorProfileRepository;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/NativeProfileRepositoryTestSupport.php';

final class AgentOrchestratorProfileRepositoryTest extends TestCase {

	use NativeProfileRepositoryTestSupport;

	public function testNativeProfilesMatchThePreviousRuntimeDefinitions(): void {
		$profiles = $this->nativeRepository()->getProfiles();
		$actual = array_map(static fn($profile): array => $profile->toArray(), $profiles);
		ksort($actual);
		$expected = json_decode(file_get_contents(__DIR__ . '/fixtures/builtin-profiles-before-refactor.json'), true, 512, JSON_THROW_ON_ERROR);
		$this->assertSame($expected, $actual);
		$this->assertCount(3, $profiles);
	}

	public function testMissingChoiceUsesNativeDefaultAndMissingLabProfileFails(): void {
		$repository = $this->nativeRepository();
		$this->assertSame('native-tool-loop', $repository->getProfile('')->getId());
		$this->assertSame('native-tool-loop', $repository->getProfile('  ')->getId());
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Orchestrator profile not found: standard');
		$repository->getProfile('standard');
	}

	public function testInvalidExplicitChoiceDoesNotResolveToDefault(): void {
		$this->expectException(\RuntimeException::class);
		$this->nativeRepository()->getProfile('!?');
	}

	public function testDuplicateDefinitionIdsAreRejected(): void {
		$definitions = $this->nativeDefinitions();
		$definitions[] = $definitions[0];
		$this->expectException(\LogicException::class);
		$this->expectExceptionMessage('Duplicate orchestrator profile id: native-tool-loop');
		$this->nativeRepository(null, $definitions)->getProfiles();
	}

	public function testForeignRuntimeIsIgnoredBeforeMaterialization(): void {
		$definitions = $this->nativeDefinitions();
		$definitions[] = new OtherRuntimeProfileDefinition();
		$this->assertCount(3, $this->nativeRepository(null, $definitions)->getProfiles());
	}

	public function testInvalidDefinitionIdIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->nativeRepository(null, [new InvalidProfileDefinition()])->getProfiles();
	}

	public function testStoredRecordCannotOverrideCodeOwnedProfile(): void {
		$store = $this->settingsStore([AgentOrchestratorProfileRepository::SETTINGS_GROUP => [
			'native-tool-loop' => ['enabled' => false, 'mode' => 'governed'],
			'custom' => ['label' => 'My profile', 'mode' => 'simple']
		]]);
		$repository = $this->nativeRepository($store);
		$this->assertTrue($repository->getProfile('native-tool-loop')->isEnabled());
		$this->assertFalse($repository->getProfile('custom')->isBuiltin());
		$this->assertSame('My profile', $repository->getProfile('custom')->getLabel());
		$this->assertFalse($store->get(AgentOrchestratorProfileRepository::SETTINGS_GROUP, 'native-tool-loop')['enabled']);
	}

	public function testCodeOwnedProfilesCannotBeOverwritten(): void {
		$this->expectException(\RuntimeException::class);
		$this->nativeRepository()->save('native-tool-loop', []);
	}

	public function testCodeOwnedProfilesCannotBeRemoved(): void {
		$this->expectException(\RuntimeException::class);
		$this->nativeRepository()->remove('native-tool-loop');
	}

	public function testNoBuiltinProfileUsesLegacySimpleModelDecision(): void {
		$repository = $this->nativeRepository($this->settingsStore());

		foreach ($repository->getProfiles() as $profile) {
			$this->assertFalse(
				$profile->getModelDecision()->getStrategy() === AgentModelDecisionConfig::STRATEGY_SIMPLE
			);
		}
	}

	public function testNativeToolLoopProfileUsesLiveNativeSemantics(): void {
		$profile = ($this->nativeRepository($this->settingsStore()))->getProfile('native-tool-loop');

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
		$profile = ($this->nativeRepository($this->settingsStore()))->getProfile('large-catalog-native');
		$config = $profile->getCapabilitySelection();

		$this->assertSame('Large catalog native tool loop', $profile->getLabel());
		$this->assertSame(AgentModelDecisionConfig::STRATEGY_NATIVE, $profile->getModelDecision()->getStrategy());
		$this->assertFalse($profile->getModelDecision()->isRepairEnabled());
		$this->assertFalse($profile->isCapabilitySelectionEnabled());
		$this->assertTrue($profile->isAiCapabilitySelectionEnabled());
		$this->assertSame(AgentCapabilitySelectionConfig::SELECTION_UNIT_SOURCE, $config->getSelectionUnit());
		$this->assertSame(16, $config->getMaxTools());
		$this->assertSame(16, $config->getMaxSources());
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


	public function testAgentSelectedNativeUsesMainAgentSourceControlWithoutPreselectionStage(): void {
		$profile = ($this->nativeRepository($this->settingsStore()))->getProfile('agent-selected-native');
		$config = $profile->getCapabilitySelection();

		$this->assertSame('Agent-selected native tool loop', $profile->getLabel());
		$this->assertSame(AgentModelDecisionConfig::STRATEGY_NATIVE_CAPABILITY, $profile->getModelDecision()->getStrategy());
		$this->assertFalse($profile->getModelDecision()->isRepairEnabled());
		$this->assertSame(32, $profile->getMaxToolLoops());
		$this->assertFalse($profile->isCapabilitySelectionEnabled());
		$this->assertFalse($profile->isAiCapabilitySelectionEnabled());
		$this->assertFalse($config->isEnabled());
		$this->assertSame(64, $config->getMaxTools());
		$this->assertSame(8, $config->getMaxSources());
		$this->assertFalse($config->isSticky());
		$this->assertSame([
			'capability-discovery',
			'model-decision',
			'action-policy',
			'tool-execution',
			'context-compaction',
			'tool-observation'
		], $profile->getStageIds());
	}

	public function testNativeCustomProfileRejectsSemanticVerificationUntilNativeVerificationIsSupported(): void {
		$repository = $this->nativeRepository($this->settingsStore());

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
		$repository = $this->nativeRepository($this->settingsStore());

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
		$repository = $this->nativeRepository($this->settingsStore([
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
		return new AgentOrchestratorProfileRepositoryTestDouble1($groups);
	}
}

require_once __DIR__ . '/AgentOrchestratorProfileRepositoryTestDouble1.php';
require_once __DIR__ . '/OtherRuntimeProfileDefinition.php';
require_once __DIR__ . '/InvalidProfileDefinition.php';
