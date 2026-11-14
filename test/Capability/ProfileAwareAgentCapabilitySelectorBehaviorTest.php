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

namespace Base3\MissionBay\Test\Capability;

use Base3\AssistantFoundation\Dto\AgentCapability;
use Base3\AssistantFoundation\Dto\AgentCapabilityCatalog;
use Base3\AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use Base3\AssistantFoundation\Dto\AgentCapabilitySelectionRequest;
use Base3\MissionBay\Capability\HybridAgentCapabilitySelector;
use Base3\MissionBay\Capability\ProfileAwareAgentCapabilitySelector;
use PHPUnit\Framework\TestCase;

final class ProfileAwareAgentCapabilitySelectorBehaviorTest extends TestCase {

	public function testSemanticRequestIsHandledByDeterministicHybridSelector(): void {
		$config = new AgentCapabilitySelectionConfig(strategy: AgentCapabilitySelectionConfig::STRATEGY_SEMANTIC, maxTools: 1, selectAllThreshold: 0);
		$selection = $this->selector()->select($this->catalog('alpha', 'beta'), new AgentCapabilitySelectionRequest(2, 'beta', $config));
		$this->assertSame(AgentCapabilitySelectionConfig::STRATEGY_HYBRID, $selection->getStrategy());
		$this->assertSame(2, $selection->getIteration());
		$this->assertSame(['beta'], $selection->getToolNames());
	}

	public function testSemanticSelectionPreservesToolExclusionFilter(): void {
		$config = new AgentCapabilitySelectionConfig(strategy: AgentCapabilitySelectionConfig::STRATEGY_SEMANTIC, maxTools: 1, selectAllThreshold: 0, excludeTools: ['beta']);
		$selection = $this->selector()->select($this->catalog('alpha', 'beta'), new AgentCapabilitySelectionRequest(1, 'beta', $config));
		$this->assertSame(['alpha'], $selection->getToolNames());
	}

	public function testSemanticSelectionPreservesMandatoryTools(): void {
		$config = new AgentCapabilitySelectionConfig(strategy: AgentCapabilitySelectionConfig::STRATEGY_SEMANTIC, maxTools: 1, selectAllThreshold: 0);
		$request = new AgentCapabilitySelectionRequest(1, 'beta', $config, [], [], ['alpha']);
		$this->assertSame(['alpha'], $this->selector()->select($this->catalog('alpha', 'beta'), $request)->getToolNames());
	}

	public function testSemanticRequestFailsWhenMandatoryToolWasFiltered(): void {
		$config = new AgentCapabilitySelectionConfig(strategy: AgentCapabilitySelectionConfig::STRATEGY_SEMANTIC, maxTools: 1, selectAllThreshold: 0, excludeTools: ['alpha']);
		$request = new AgentCapabilitySelectionRequest(1, '', $config, [], [], ['alpha']);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Mandatory capabilities are unavailable');
		$this->selector()->select($this->catalog('alpha', 'beta'), $request);
	}

	public function testExplicitAllStrategyIsNotChangedByAdapter(): void {
		$config = new AgentCapabilitySelectionConfig(strategy: AgentCapabilitySelectionConfig::STRATEGY_ALL);
		$selection = $this->selector()->select($this->catalog('alpha', 'beta'), new AgentCapabilitySelectionRequest(1, '', $config));
		$this->assertSame(AgentCapabilitySelectionConfig::STRATEGY_ALL, $selection->getStrategy());
		$this->assertSame(['alpha', 'beta'], $selection->getToolNames());
	}

	public function testHybridSelectionIsNotChangedByAdapter(): void {
		$config = new AgentCapabilitySelectionConfig(strategy: AgentCapabilitySelectionConfig::STRATEGY_HYBRID, maxTools: 1, selectAllThreshold: 0);
		$selection = $this->selector()->select($this->catalog('alpha', 'beta'), new AgentCapabilitySelectionRequest(1, 'beta', $config));
		$this->assertSame(AgentCapabilitySelectionConfig::STRATEGY_HYBRID, $selection->getStrategy());
		$this->assertSame(['beta'], $selection->getToolNames());
	}

	private function selector(): ProfileAwareAgentCapabilitySelector {
		return new ProfileAwareAgentCapabilitySelector(new HybridAgentCapabilitySelector());
	}

	private function catalog(string ...$names): AgentCapabilityCatalog {
		$items = [];
		foreach($names as $name) {
			$items[] = new AgentCapability($name, $name, 'Capability ' . $name, 'general', [], 10, [
				'type' => 'function', 'function' => ['name' => $name, 'parameters' => ['type' => 'object', 'properties' => []]]
			]);
		}
		return new AgentCapabilityCatalog($items);
	}
}
