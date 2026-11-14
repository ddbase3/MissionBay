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

namespace Base3\MissionBay\Test\Dto\Assistant;

use Base3\AssistantFoundation\Dto\AgentCapabilityCatalog;
use Base3\MissionBay\Dto\Assistant\AgentAssistantToolSetup;
use Base3\MissionBay\Profile\ProfilePlan;
use Base3\MissionBay\Profile\ToolFilterReport;
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
