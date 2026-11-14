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

namespace Base3\MissionBay\Test\Resource\AgentTool\SystemStatus;

use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Resource\AgentTool\SystemStatus\SystemStatusAgentTool;
use PHPUnit\Framework\TestCase;

final class SystemStatusAgentToolTest extends TestCase {

	public function testUnknownToolNameIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		(new SystemStatusAgentTool())->callTool('run_command', [], new AgentContext());
	}

	public function testReadOnlyStatusDoesNotExposeSensitiveRequestArguments(): void {
		$result = (new SystemStatusAgentTool())->callTool('system_status', ['private' => 'should-not-be-returned'], new AgentContext());
		$this->assertSame(PHP_VERSION, $result['php']);
		$this->assertArrayHasKey('os', $result);
		$this->assertArrayHasKey('uptime', $result);
		$this->assertArrayHasKey('load', $result);
		$this->assertArrayHasKey('memory', $result);
		$this->assertArrayHasKey('disk', $result);
		$this->assertArrayNotHasKey('private', $result);
	}

	public function testDefinitionDeclaresReadOnlyOperation(): void {
		$definitions = (new SystemStatusAgentTool())->getToolDefinitions();
		$this->assertCount(1, $definitions);
		$this->assertSame('system_status', $definitions[0]['function']['name']);
		$this->assertFalse($definitions[0]['mutation']);
		$this->assertTrue($definitions[0]['readOnlyHint']);
	}
}
