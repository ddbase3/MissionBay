<?php declare(strict_types=1);

namespace MissionBay\Test\Dto\Orchestrator;

use InvalidArgumentException;
use MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use PHPUnit\Framework\TestCase;

final class AgentModelDecisionConfigTest extends TestCase {

	public function testDefaultAndNamedStrategiesDescribeStreamingAndRepair(): void {
		$this->assertSame(AgentModelDecisionConfig::STRATEGY_AI_GUARDED, AgentModelDecisionConfig::aiGuarded()->getStrategy());
		$this->assertTrue(AgentModelDecisionConfig::aiGuarded()->isRepairEnabled());
		$this->assertFalse(AgentModelDecisionConfig::simple()->usesNativeStreaming());
		$this->assertTrue(AgentModelDecisionConfig::native()->usesNativeStreaming());
		$this->assertTrue(AgentModelDecisionConfig::nativeCapability()->usesNativeStreaming());
		$this->assertFalse(AgentModelDecisionConfig::native()->isRepairEnabled());
	}

	public function testFromArrayDisablesRepairForNativeStrategies(): void {
		$config = AgentModelDecisionConfig::fromArray(['strategy' => ' NATIVE-MODEL-DECISION ', 'repair_enabled' => true, 'confidence_threshold' => 1.7]);
		$this->assertSame(AgentModelDecisionConfig::STRATEGY_NATIVE, $config->getStrategy());
		$this->assertFalse($config->isRepairEnabled());
		$this->assertSame(1.0, $config->getConfidenceThreshold());
		$this->assertSame($config->toArray()['confidence_threshold'], $config->getConfidenceThreshold());
	}

	public function testFromArrayClampsLowerBoundAndDefaultsEmptyStrategy(): void {
		$config = AgentModelDecisionConfig::fromArray(['strategy' => '', 'confidence_threshold' => -1, 'repair_enabled' => 'no']);
		$this->assertSame(AgentModelDecisionConfig::STRATEGY_AI_GUARDED, $config->getStrategy());
		$this->assertSame(0.0, $config->getConfidenceThreshold());
		$this->assertFalse($config->isRepairEnabled());
	}

	public function testConstructorRejectsUnsupportedStrategyAndConfidence(): void {
		foreach (['unknown', ''] as $strategy) {
			try {
				new AgentModelDecisionConfig($strategy);
				$this->fail('Invalid strategy was accepted.');
			} catch (InvalidArgumentException) {
				$this->addToAssertionCount(1);
			}
		}
		foreach ([-0.01, 1.01] as $confidence) {
			try {
				new AgentModelDecisionConfig(confidenceThreshold: $confidence);
				$this->fail('Out-of-range confidence was accepted.');
			} catch (InvalidArgumentException) {
				$this->addToAssertionCount(1);
			}
		}
	}
}
