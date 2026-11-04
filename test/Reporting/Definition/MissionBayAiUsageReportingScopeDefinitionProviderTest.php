<?php declare(strict_types=1);

namespace MissionBay\Test\Reporting\Definition;

use MissionBay\Reporting\Definition\MissionBayAiUsageReportingScopeDefinitionProvider;
use ResourceFoundation\Api\IReportingScopeDefinitionProvider;
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
