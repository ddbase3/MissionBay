<?php declare(strict_types=1);

namespace MissionBay\Test\Capability;

use AssistantFoundation\Dto\AgentCapability;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use AssistantFoundation\Dto\AgentCapabilitySelectionRequest;
use MissionBay\Capability\HybridAgentCapabilitySelector;
use MissionBay\Capability\ProfileAwareAgentCapabilitySelector;
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
