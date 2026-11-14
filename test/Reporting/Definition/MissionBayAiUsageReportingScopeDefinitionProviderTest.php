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

namespace Base3\MissionBay\Test\Reporting\Definition;

use Base3\MissionBay\Reporting\Definition\MissionBayAiUsageReportingScopeDefinitionProvider;
use Base3\ResourceFoundation\Api\IReportingScopeDefinitionProvider;
use PHPUnit\Framework\TestCase;

final class MissionBayAiUsageReportingScopeDefinitionProviderTest extends TestCase {

	public function testDefinitionProviderHasStableDiscoverableName(): void {
		$this->assertSame('missionbayaiusagereportingscopedefinitionprovider', MissionBayAiUsageReportingScopeDefinitionProvider::getName());
	}

	public function testDefinitionProviderUsesFoundationContract(): void {
		$this->assertTrue(new MissionBayAiUsageReportingScopeDefinitionProvider() instanceof IReportingScopeDefinitionProvider);
	}

	public function testReportingScopeHasStableTechnicalIdentity(): void {
		$definition = (new MissionBayAiUsageReportingScopeDefinitionProvider())->getReportingScopeDefinition();
		$this->assertSame('missionbay_ai', $definition->id);
		$this->assertSame('MissionBay AI', $definition->label);
	}

	public function testReportingSchemaAndReportScopesUseSameNamespace(): void {
		$definition = (new MissionBayAiUsageReportingScopeDefinitionProvider())->getReportingScopeDefinition();
		$this->assertSame(['missionbay_ai'], $definition->querySchemaScopes);
		$this->assertSame(['missionbay_ai'], $definition->reportScopes);
	}

	public function testNoMaterializationScopeIsInferredFromReportScope(): void {
		$this->assertSame([], (new MissionBayAiUsageReportingScopeDefinitionProvider())->getReportingScopeDefinition()->materializationScopes);
	}
}
