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

namespace Base3\MissionBay\Test\Dto\Orchestrator;

use InvalidArgumentException;
use Base3\MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
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
