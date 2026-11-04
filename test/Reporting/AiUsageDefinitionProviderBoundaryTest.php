<?php declare(strict_types=1);

namespace MissionBay\Test\Reporting;

use Base3\Settings\Api\ISettingsStore;
use MissionBay\Reporting\Definition\MissionBayAiUsageReportConfigDefinitionProvider;
use MissionBay\Reporting\Definition\MissionBayAiUsageQuerySchemaDefinitionProvider;
use MissionBay\Reporting\Settings\AiUsageReportingSettings;
use PHPUnit\Framework\TestCase;

final class AiUsageDefinitionProviderBoundaryTest extends TestCase {

	public function testReportProviderReadsOnlyVizionDefinitions(): void {
		$settings = $this->createMock(ISettingsStore::class);
		$rows = ['overview' => ['definition' => ['title' => 'Overview']]];
		$settings->expects($this->once())->method('getGroup')->with(AiUsageReportingSettings::GROUP_VIZION)->willReturn($rows);
		$provider = new MissionBayAiUsageReportConfigDefinitionProvider($settings);
		$this->assertSame('missionbay_ai', $provider->getScope());
		$this->assertSame($rows, $provider->getDefinitions());
	}

	public function testQueryProviderReadsOnlyDataHawkDefinitions(): void {
		$settings = $this->createMock(ISettingsStore::class);
		$rows = ['usage' => ['definition' => ['table' => 'usage']]];
		$settings->expects($this->once())->method('getGroup')->with(AiUsageReportingSettings::GROUP_DATAHAWK_SOURCE)->willReturn($rows);
		$provider = new MissionBayAiUsageQuerySchemaDefinitionProvider($settings);
		$this->assertSame('missionbay_ai', $provider->getScope());
		$this->assertSame($rows, $provider->getDefinitions());
	}

	public function testBothProvidersPassThroughEmptyDefinitions(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('getGroup')->willReturn([]);
		$this->assertSame([], (new MissionBayAiUsageReportConfigDefinitionProvider($settings))->getDefinitions());
		$this->assertSame([], (new MissionBayAiUsageQuerySchemaDefinitionProvider($settings))->getDefinitions());
	}

	public function testBothProviderNamesAreGloballyDistinct(): void {
		$this->assertSame('missionbayaiusagereportconfigdefinitionprovider', MissionBayAiUsageReportConfigDefinitionProvider::getName());
		$this->assertSame('missionbayaiusagequeryschemadefinitionprovider', MissionBayAiUsageQuerySchemaDefinitionProvider::getName());
	}
}
