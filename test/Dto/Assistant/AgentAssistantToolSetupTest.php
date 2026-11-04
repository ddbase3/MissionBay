<?php declare(strict_types=1);

namespace MissionBay\Test\Dto\Assistant;

use AssistantFoundation\Dto\AgentCapabilityCatalog;
use MissionBay\Dto\Assistant\AgentAssistantToolSetup;
use MissionBay\Profile\ProfilePlan;
use MissionBay\Profile\ToolFilterReport;
use PHPUnit\Framework\TestCase;

final class AgentAssistantToolSetupTest extends TestCase {

	public function testSetupPreservesProfileFilteringAndMissingToolSignals(): void {
		$catalog = new AgentCapabilityCatalog();
		$plan = new ProfilePlan('review', allowedTools: ['read'], requiredTools: ['write']);
		$report = new ToolFilterReport(['write']);
		$setup = new AgentAssistantToolSetup([], [['function' => ['name' => 'read']]], $catalog, $plan, $report, ['read'], true, ['write']);
		$this->assertSame($catalog, $setup->getCatalog());
		$this->assertSame($plan, $setup->getEffectivePlan());
		$this->assertSame($report, $setup->getReport());
		$this->assertSame(['read'], $setup->getAllowedToolNames());
		$this->assertTrue($setup->wasProfileUnavailable());
		$this->assertSame(['write'], $setup->getMissingRequiredTools());
		$this->assertSame('read', $setup->getToolDefs()[0]['function']['name']);
	}

	public function testUnrestrictedSetupKeepsNullAllowedListDistinctFromEmpty(): void {
		$setup = new AgentAssistantToolSetup([], [], new AgentCapabilityCatalog(), new ProfilePlan('default'), new ToolFilterReport([]), null);
		$this->assertNull($setup->getAllowedToolNames());
		$this->assertFalse($setup->wasProfileUnavailable());
		$this->assertSame([], $setup->getTools());
		$this->assertSame([], $setup->getToolDefs());
		$this->assertSame([], $setup->getMissingRequiredTools());
	}
}
