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

namespace Base3\MissionBay\Test\Profile;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\MissionBay\Api\IAgentProfileSelector;
use Base3\MissionBay\Capability\AgentCapabilityCatalogBuilder;
use Base3\MissionBay\Profile\AgentAssistantToolSetupFactory;
use Base3\MissionBay\Profile\ProfilePlan;
use Base3\MissionBay\Profile\ToolGuardAgentTool;
use PHPUnit\Framework\TestCase;

final class AgentAssistantToolSetupFactoryTest extends TestCase {

	public function testDefaultWithoutProfileSelectorRetainsAllTools(): void {
		$tool = $this->tool('read', 'write');
		$setup = $this->factory()->create([$tool], null, 'request', 'system', $this->createStub(IAgentContext::class));
		$this->assertSame('default', $setup->getEffectivePlan()->getProfileName());
		$this->assertNull($setup->getAllowedToolNames());
		$this->assertSame([$tool], $setup->getTools());
		$this->assertSame(['read', 'write'], array_column(array_column($setup->getToolDefs(), 'function'), 'name'));
		$this->assertTrue($setup->getReport()->isFeasible());
		$this->assertFalse($setup->wasProfileUnavailable());
	}

	public function testExplicitlyAllowedToolsAreGuardedAndExcludedFromCatalog(): void {
		$tool = $this->tool('read', 'write');
		$selector = $this->selector([new ProfilePlan('limited', '', ['read'], ['read'])]);
		$context = $this->createStub(IAgentContext::class);
		$setup = $this->factory()->create([$tool], $selector, 'question', 'system', $context);
		$this->assertSame(['read'], $setup->getAllowedToolNames());
		$this->assertSame(['read'], array_column(array_column($setup->getToolDefs(), 'function'), 'name'));
		$this->assertTrue($setup->getTools()[0] instanceof ToolGuardAgentTool);
		$this->assertSame(['name' => 'read', 'arguments' => []], $setup->getTools()[0]->callTool('read', [], $context));
		$this->assertCount(1, $setup->getCatalog()->all());
		try {
			$setup->getTools()[0]->callTool('write', [], $context);
			$this->fail('Filtered write tool could execute.');
		} catch (\RuntimeException $exception) {
			$this->assertStringContainsString('not allowed', $exception->getMessage());
		}
	}

	public function testPlansMergeSystemInstructionsAndDistinctRequirements(): void {
		$selector = $this->selector([
			new ProfilePlan('first', '  Alpha  ', ['write', 'read'], ['write', 'read']),
			new ProfilePlan('second', 'Beta', ['read', 'search'], ['read', 'search'])
		]);
		$setup = $this->factory()->create([$this->tool('read', 'write', 'search')], $selector, '', '', $this->createStub(IAgentContext::class));
		$this->assertSame('effective', $setup->getEffectivePlan()->getProfileName());
		$this->assertSame("Alpha\n\nBeta", $setup->getEffectivePlan()->getSystemAppend());
		$this->assertSame(['read', 'search', 'write'], $setup->getAllowedToolNames());
		$this->assertSame(['read', 'search', 'write'], $setup->getEffectivePlan()->getRequiredTools());
		$this->assertFalse($setup->wasProfileUnavailable());
	}

	public function testMissingRequiredToolCausesUnavailableProfileAndDefaultFallback(): void {
		$tool = $this->tool('read', 'write');
		$setup = $this->factory()->create([$tool], $this->selector([
			new ProfilePlan('restrictive', 'custom', ['read'], ['missing'])
		]), '', '', $this->createStub(IAgentContext::class));
		$this->assertTrue($setup->wasProfileUnavailable());
		$this->assertSame(['missing'], $setup->getMissingRequiredTools());
		$this->assertSame('default', $setup->getEffectivePlan()->getProfileName());
		$this->assertNull($setup->getAllowedToolNames());
		$this->assertSame([$tool], $setup->getTools());
		$this->assertCount(2, $setup->getToolDefs());
	}

	public function testEmptyAllowedListSelectsNothingWithoutFallingBack(): void {
		$setup = $this->factory()->create([$this->tool('read')], $this->selector([
			new ProfilePlan('empty', null, [], [])
		]), '', '', $this->createStub(IAgentContext::class));
		$this->assertSame([], $setup->getAllowedToolNames());
		$this->assertSame([], $setup->getToolDefs());
		$this->assertFalse($setup->wasProfileUnavailable());
		$this->assertSame('effective', $setup->getEffectivePlan()->getProfileName());
	}

	public function testInvalidPlansAreIgnoredAndNoPlansYieldDefault(): void {
		foreach ([[], ['invalid', null]] as $plans) {
			$setup = $this->factory()->create([$this->tool('read')], $this->selector($plans), '', '', $this->createStub(IAgentContext::class));
			$this->assertSame($plans === [] ? 'default' : 'effective', $setup->getEffectivePlan()->getProfileName());
			$this->assertNull($setup->getAllowedToolNames());
		}
	}

	private function factory(): AgentAssistantToolSetupFactory {
		return new AgentAssistantToolSetupFactory(new AgentCapabilityCatalogBuilder());
	}

	private function tool(string ...$names): ProfileToolsTestAgentTool {
		return new ProfileToolsTestAgentTool(array_map(static fn(string $name): array => [
			'function' => ['name' => $name, 'parameters' => ['type' => 'object', 'properties' => []]]
		], $names));
	}

	/** @param array<int,mixed> $plans */
	private function selector(array $plans): IAgentProfileSelector {
		$selector = $this->createStub(IAgentProfileSelector::class);
		$selector->method('selectPlans')->willReturn($plans);
		return $selector;
	}
}
