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

namespace Base3\MissionBay\Test\Dto;

use Base3\MissionBay\Dto\AgentInfoRequest;
use PHPUnit\Framework\TestCase;

final class AgentInfoRequestTest extends TestCase {

	public function testDefaultAndTopicDiscovery(): void {
		$request = new AgentInfoRequest();
		$this->assertSame('summary', $request->scope);
		$this->assertSame(5, $request->limit);
		$this->assertSame(0, $request->offset);
		$this->assertFalse($request->isTopicDiscovery());
		$discovery = new AgentInfoRequest('topics', 'anything', 'find', 2, 1);
		$this->assertTrue($discovery->isTopicDiscovery());
		$this->assertSame('anything', $discovery->query);
	}
}
