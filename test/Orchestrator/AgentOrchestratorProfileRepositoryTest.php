<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use MissionBay\Orchestrator\Profile\AgentOrchestratorProfileRepository;
use PHPUnit\Framework\TestCase;

final class AgentOrchestratorProfileRepositoryTest extends TestCase {

	public function testExposesFiveBuiltinProfilesIncludingLargeCatalog(): void {
		$repository = new AgentOrchestratorProfileRepository($this->settingsStore());
		$profiles = $repository->getProfiles();

		$this->assertCount(5, $profiles);
		$this->assertArrayHasKey('large-catalog', $profiles);

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

	public function testBuiltinProfilesUseAiGuardedModelDecisionByDefault(): void {
		$repository = new AgentOrchestratorProfileRepository($this->settingsStore());

		foreach ($repository->getProfiles() as $profile) {
			$this->assertSame(AgentModelDecisionConfig::STRATEGY_AI_GUARDED, $profile->getModelDecision()->getStrategy());
			$this->assertTrue($profile->getModelDecision()->isRepairEnabled());
		}
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
