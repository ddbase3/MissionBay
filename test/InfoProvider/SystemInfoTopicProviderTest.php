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

namespace Base3\MissionBay\Test\InfoProvider;

use Base3\MissionBay\Dto\AgentInfoRequest;
use Base3\MissionBay\InfoProvider\SystemInfoTopicProvider;
use PHPUnit\Framework\TestCase;

final class SystemInfoTopicProviderTest extends TestCase {

	public function testTopicAliasesAndPriority(): void {
		$provider = new SystemInfoTopicProvider();
		$this->assertTrue($provider->supports('SYSTEM'));
		$this->assertTrue($provider->supports('diagnostics'));
		$this->assertFalse($provider->supports('network-administration'));
		$this->assertSame(50, $provider->getPriority());
	}

	public function testDetailRejectsUnknownSection(): void {
		$provider = new SystemInfoTopicProvider();
		$result = $provider->handle(new AgentInfoRequest('system', 'not_a_section', 'detail'));
		$this->assertFalse($result->success);
		$this->assertSame('unknown_section', $result->errors[0]['code']);
		$this->assertNotEmpty($result->suggestions);
	}

	public function testFindReportsSectionPaginationAndSummaryHasPhpMetadata(): void {
		$provider = new SystemInfoTopicProvider();
		$found = $provider->handle(new AgentInfoRequest('system', 'php', 'find', 2));
		$this->assertTrue($found->success);
		$this->assertTrue($found->paging['returned'] <= 2);
		$summary = $provider->handle(new AgentInfoRequest('system', '', 'summary'));
		$this->assertSame('system', $summary->topic);
		$this->assertArrayHasKey('php', $summary->detail);
		$this->assertArrayHasKey('memory', $summary->detail);
		$this->assertSame([], $provider->handle(new AgentInfoRequest('system', '', 'link'))->links);
	}
}
