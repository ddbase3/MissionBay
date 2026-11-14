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

use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Dto\AgentCapability;
use Base3\AssistantFoundation\Dto\AgentCapabilityCatalog;
use Base3\AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use Base3\AssistantFoundation\Dto\AgentCapabilitySelectionRequest;
use Base3\MissionBay\Capability\HybridAgentCapabilitySelector;
use Base3\MissionBay\Capability\SemanticAgentCapabilitySelector;
use PHPUnit\Framework\TestCase;

final class SemanticAgentCapabilitySelectorFallbackTest extends TestCase {

	public function testMissingModelFallsBackToHybridRanking(): void {
		$selection = $this->select(new AgentCapabilitySelectionConfig(maxTools: 1, selectAllThreshold: 0), 'beta');
		$this->assertSame(['beta'], $selection->getToolNames());
		$this->assertSame(AgentCapabilitySelectionConfig::STRATEGY_SEMANTIC, $selection->getStrategy());
		$this->assertTrue(in_array('semantic-model-unavailable', $selection->getReasons()['beta'], true));
	}

	public function testDisabledSelectionPreservesEligibleCapabilities(): void {
		$selection = $this->select(new AgentCapabilitySelectionConfig(enabled: false, maxTools: 1, selectAllThreshold: 0));
		$this->assertSame(['alpha', 'beta'], $selection->getToolNames());
		$this->assertTrue(in_array('semantic-small-pool', $selection->getReasons()['alpha'], true));
	}

	public function testSmallPoolNeedsNoModelAndRetainsConfiguredLimit(): void {
		$selection = $this->select(new AgentCapabilitySelectionConfig(maxTools: 2, selectAllThreshold: 2));
		$this->assertSame(['alpha', 'beta'], $selection->getToolNames());
		$this->assertTrue(in_array('semantic-small-pool', $selection->getReasons()['beta'], true));
	}

	public function testProviderExceptionFallsBackWithoutLeakingIt(): void {
		$model = $this->createStub(IAiChatModel::class);
		$model->method('complete')->willThrowException(new \RuntimeException('upstream credentials must not leak'));
		$selection = $this->select(new AgentCapabilitySelectionConfig(maxTools: 1, selectAllThreshold: 0), 'alpha', $model);
		$this->assertSame(['alpha'], $selection->getToolNames());
		$this->assertTrue(in_array('semantic-provider-fallback', $selection->getReasons()['alpha'], true));
		$this->assertStringNotContainsString('credentials', json_encode($selection->toArray()));
	}

	private function select(AgentCapabilitySelectionConfig $config, string $text = '', ?IAiChatModel $model = null): \Base3\AssistantFoundation\Dto\AgentCapabilitySelection {
		$catalog = new AgentCapabilityCatalog([
			new AgentCapability('alpha', 'Alpha', 'First', 'test', [], 1, ['function' => ['name' => 'alpha']]),
			new AgentCapability('beta', 'Beta', 'Second', 'test', [], 1, ['function' => ['name' => 'beta']])
		]);
		return (new SemanticAgentCapabilitySelector(new HybridAgentCapabilitySelector()))->select(
			$catalog,
			new AgentCapabilitySelectionRequest(2, $text, $config, model: $model)
		);
	}
}
