<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator\Profile;

use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use InvalidArgumentException;
use MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use MissionBay\Orchestrator\Profile\AgentOrchestratorProfile;
use PHPUnit\Framework\TestCase;

final class AgentOrchestratorProfileTest extends TestCase {

	public function testMandatoryStagesAndOptionalPipelineOrder(): void {
		$profile = $this->profile([
			'capabilityDiscoveryEnabled' => true,
			'capabilitySelectionEnabled' => true,
			'contextCompactionEnabled' => true,
			'semanticVerificationEnabled' => true
		]);
		$this->assertSame([
			'capability-discovery', 'capability-selection', 'model-decision', 'action-policy',
			'tool-execution', 'context-compaction', 'tool-observation', 'semantic-verification'
		], $profile->getStageIds());
		$this->assertSame(true, $profile->getOptionalStages()['semantic-verification']);
		$this->assertSame('profile', $profile->getId());
		$this->assertSame('Profile', $profile->getLabel());
		$this->assertSame('test', $profile->getDescription());
		$this->assertSame('standard', $profile->getMode());
		$this->assertSame(4, $profile->getMaxToolLoops());
		$this->assertSame($profile->getStageIds(), $profile->toArray()['stage_ids']);
	}

	public function testMinimalPipelineKeepsRequiredStages(): void {
		$profile = $this->profile(['enabled' => false, 'builtin' => true, 'deliberatePlanningEnabled' => true]);
		$this->assertSame(['model-decision', 'action-policy', 'tool-execution', 'tool-observation'], $profile->getStageIds());
		$this->assertFalse($profile->isEnabled());
		$this->assertTrue($profile->isBuiltin());
		$this->assertTrue($profile->isDeliberatePlanningEnabled());
		$this->assertSame(AgentModelDecisionConfig::STRATEGY_AI_GUARDED, $profile->getModelDecision()->getStrategy());
	}

	public function testAiCapabilitySelectionIsAnAlternativeToDeterministicSelection(): void {
		$profile = $this->profile(['aiCapabilitySelectionEnabled' => true, 'capabilitySelection' => new AgentCapabilitySelectionConfig(selectionUnit: AgentCapabilitySelectionConfig::SELECTION_UNIT_SOURCE)]);
		$this->assertSame('ai-capability-selection', $profile->getStageIds()[0]);
		$this->assertTrue($profile->isAiCapabilitySelectionEnabled());
		$this->assertFalse($profile->isCapabilitySelectionEnabled());
	}

	public function testConflictingOrInvalidProfilesAreRejected(): void {
		$invalid = [
			['id' => ' '], ['mode' => 'other'], ['maxToolLoops' => 0], ['maxToolLoops' => 101],
			['capabilitySelectionEnabled' => true, 'aiCapabilitySelectionEnabled' => true],
			['capabilitySelection' => new AgentCapabilitySelectionConfig(selectionUnit: AgentCapabilitySelectionConfig::SELECTION_UNIT_SOURCE)],
			['modelDecision' => AgentModelDecisionConfig::native(), 'semanticVerificationEnabled' => true]
		];
		foreach ($invalid as $overrides) {
			try {
				$this->profile($overrides);
				$this->fail('An invalid orchestrator profile was accepted.');
			} catch (InvalidArgumentException) {
				$this->addToAssertionCount(1);
			}
		}
	}

	private function profile(array $overrides = []): AgentOrchestratorProfile {
		$defaults = [
			'id' => 'profile', 'label' => 'Profile', 'description' => 'test', 'enabled' => true,
			'mode' => 'standard', 'maxToolLoops' => 4,
			'capabilityDiscoveryEnabled' => false, 'capabilitySelectionEnabled' => false,
			'aiCapabilitySelectionEnabled' => false, 'contextCompactionEnabled' => false,
			'semanticVerificationEnabled' => false, 'capabilitySelection' => new AgentCapabilitySelectionConfig(),
			'deliberatePlanningEnabled' => false, 'builtin' => false, 'modelDecision' => AgentModelDecisionConfig::aiGuarded()
		];
		return new AgentOrchestratorProfile(...array_replace($defaults, $overrides));
	}
}
