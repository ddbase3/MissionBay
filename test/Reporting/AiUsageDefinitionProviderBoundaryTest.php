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

namespace Base3\MissionBay\Test\Reporting;

use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Reporting\Definition\MissionBayAiUsageReportConfigDefinitionProvider;
use Base3\MissionBay\Reporting\Definition\MissionBayAiUsageQuerySchemaDefinitionProvider;
use Base3\MissionBay\Reporting\Settings\AiUsageReportingSettings;
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
