<?php declare(strict_types=1);

namespace MissionBay\Test\Capability;

use AssistantFoundation\Dto\AgentCapability;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use AssistantFoundation\Dto\AgentCapabilitySelectionRequest;
use MissionBay\Capability\HybridAgentCapabilitySelector;
use PHPUnit\Framework\TestCase;

final class HybridAgentCapabilitySelectorTest extends TestCase {

	public function testDisabledSelectionReturnsAllEligibleCapabilities(): void {
		$catalog = new AgentCapabilityCatalog([$this->capability('alpha'), $this->capability('beta')]);
		$selection = $this->select($catalog, new AgentCapabilitySelectionConfig(enabled: false, maxTools: 1, selectAllThreshold: 0));
		$this->assertSame(['alpha', 'beta'], $selection->getToolNames());
		$this->assertSame(2, $selection->getCatalogSize());
		$this->assertSame(2, $selection->getEligibleSize());
		$this->assertSame(AgentCapabilitySelectionConfig::STRATEGY_ALL, $selection->getStrategy());
		$this->assertSame(['disabled'], $selection->getReasons()['alpha']);
	}

	public function testExplicitAllStrategyPreservesCatalogOrderWithinFilters(): void {
		$catalog = new AgentCapabilityCatalog([$this->capability('alpha'), $this->capability('beta'), $this->capability('gamma')]);
		$selection = $this->select($catalog, new AgentCapabilitySelectionConfig(strategy: AgentCapabilitySelectionConfig::STRATEGY_ALL, excludeTools: ['beta']));
		$this->assertSame(['alpha', 'gamma'], $selection->getToolNames());
		$this->assertSame(3, $selection->getCatalogSize());
		$this->assertSame(2, $selection->getEligibleSize());
	}

	public function testFiltersApplyIncludesExcludesTagsAndCategoriesCaseInsensitively(): void {
		$catalog = new AgentCapabilityCatalog([
			$this->capability('a', 'DOCUMENTS', ['SEARCH']),
			$this->capability('b', 'documentS', ['Search', 'private']),
			$this->capability('c', 'reports', ['search']),
			$this->capability('d', 'documents', ['other'])
		]);
		$config = new AgentCapabilitySelectionConfig(
			selectAllThreshold: 0,
			includeTools: ['a', 'b', 'c', 'd'],
			excludeTools: ['d'],
			includeTags: ['search'],
			excludeTags: ['PRIVATE'],
			includeCategories: ['documents'],
			excludeCategories: ['reports']
		);
		$this->assertSame(['a'], $this->select($catalog, $config)->getToolNames());
	}

	public function testUnfilteredMandatoryNameIsNotSilentlyIgnored(): void {
		$catalog = new AgentCapabilityCatalog([$this->capability('read')]);
		try {
			$this->select($catalog, new AgentCapabilitySelectionConfig(selectAllThreshold: 0, alwaysAvailable: ['write']));
			$this->fail('A missing required capability must fail.');
		} catch (\RuntimeException $e) {
			$this->assertStringContainsString('Mandatory capabilities are unavailable', $e->getMessage());
		}
	}

	public function testRequiredToolRemovedByFiltersIsReported(): void {
		$catalog = new AgentCapabilityCatalog([$this->capability('read'), $this->capability('write')]);
		$this->expectException(\RuntimeException::class);
		$this->select($catalog, new AgentCapabilitySelectionConfig(selectAllThreshold: 0, excludeTools: ['write']), '', [], [], ['write']);
	}

	public function testRequiredToolsCannotExceedMaxTools(): void {
		$catalog = new AgentCapabilityCatalog([$this->capability('a'), $this->capability('b')]);
		try {
			$this->select($catalog, new AgentCapabilitySelectionConfig(maxTools: 1, selectAllThreshold: 0), '', [], [], ['a', 'b']);
			$this->fail('A mandatory tool count greater than the limit must fail.');
		} catch (\RuntimeException $e) {
			$this->assertStringContainsString('maxTools is 1', $e->getMessage());
		}
	}

	public function testMandatoryToolWinsEvenWhenAnotherToolMatchesTheQuery(): void {
		$catalog = new AgentCapabilityCatalog([$this->capability('mandatory'), $this->capability('weather')]);
		$selection = $this->select($catalog, new AgentCapabilitySelectionConfig(maxTools: 1, selectAllThreshold: 0), 'show weather', [], [], ['mandatory']);
		$this->assertSame(['mandatory'], $selection->getToolNames());
		$this->assertTrue(in_array('mandatory', $selection->getReasons()['mandatory'], true));
		$this->assertTrue($selection->getScores()['mandatory'] >= 1000.0);
	}

	public function testNameMatchOutranksUnrelatedHighPriorityTool(): void {
		$catalog = new AgentCapabilityCatalog([$this->capability('archive', priority: 500), $this->capability('search_notes', priority: 1)]);
		$selection = $this->select($catalog, new AgentCapabilitySelectionConfig(maxTools: 1, selectAllThreshold: 0), 'please search_notes for me');
		$this->assertSame(['search_notes'], $selection->getToolNames());
		$this->assertTrue(in_array('name-match', $selection->getReasons()['search_notes'], true));
	}

	public function testRecentAndStickySelectionBreaksRelevanceTies(): void {
		$catalog = new AgentCapabilityCatalog([$this->capability('alpha'), $this->capability('beta')]);
		$config = new AgentCapabilitySelectionConfig(maxTools: 1, selectAllThreshold: 0);
		$this->assertSame(['beta'], $this->select($catalog, $config, '', ['beta'])->getToolNames());
		$this->assertSame(['beta'], $this->select($catalog, $config, '', [], ['beta'])->getToolNames());
		$this->assertSame(['alpha'], $this->select($catalog, new AgentCapabilitySelectionConfig(maxTools: 1, selectAllThreshold: 0, sticky: false), '', ['beta'])->getToolNames());
	}

	public function testSmallPoolThresholdSelectsAllRatherThanTruncating(): void {
		$catalog = new AgentCapabilityCatalog([$this->capability('alpha'), $this->capability('beta')]);
		$selection = $this->select($catalog, new AgentCapabilitySelectionConfig(maxTools: 3, selectAllThreshold: 2));
		$this->assertSame(['alpha', 'beta'], $selection->getToolNames());
		$this->assertSame(AgentCapabilitySelectionConfig::STRATEGY_ALL, $selection->getStrategy());
		$this->assertSame(['small-pool-or-all'], $selection->getReasons()['beta']);
	}

	private function capability(string $name, string $category = 'tools', array $tags = [], int $priority = 1): AgentCapability {
		return new AgentCapability($name, $name, 'Test capability', $category, $tags, $priority, [
			'type' => 'function',
			'function' => ['name' => $name, 'parameters' => ['type' => 'object', 'properties' => []]]
		]);
	}

	private function select(
		AgentCapabilityCatalog $catalog,
		AgentCapabilitySelectionConfig $config,
		string $text = '',
		array $previous = [],
		array $recent = [],
		array $required = []
	): \AssistantFoundation\Dto\AgentCapabilitySelection {
		return (new HybridAgentCapabilitySelector())->select($catalog, new AgentCapabilitySelectionRequest(3, $text, $config, $previous, $recent, $required));
	}
}
