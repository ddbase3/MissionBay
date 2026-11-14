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

namespace Base3\MissionBay\Test\Resource;

use PHPUnit\Framework\TestCase;
use Base3\MissionBay\Resource\AgentTool\CurrentTime\CurrentTimeAgentTool;
use Base3\MissionBay\Api\IAgentTool;
use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentMemory;

class CurrentTimeAgentToolTest extends TestCase {

	public function testImplementsAgentToolInterface(): void {
		$tool = new CurrentTimeAgentTool('id1');
		$this->assertInstanceOf(IAgentTool::class, $tool);
	}

	public function testGetNameAndDescription(): void {
		$tool = new CurrentTimeAgentTool('id1');

		$this->assertSame('currenttimeagenttool', CurrentTimeAgentTool::getName());
		$this->assertSame(
			'Provides the current server time and timezone. Useful for scheduling and answering time-related questions.',
			$tool->getDescription()
		);
	}

	public function testGetToolDefinitionsReturnsExpectedSchema(): void {
		$tool = new CurrentTimeAgentTool('id1');

		$defs = $tool->getToolDefinitions();
		$this->assertIsArray($defs);
		$this->assertCount(1, $defs);

		$def = $defs[0];
		$this->assertSame('function', $def['type']);
		$this->assertSame('Current Time Lookup', $def['label']);
		$this->assertSame('get_current_time', $def['function']['name']);
	}

	public function testCallToolThrowsForUnsupportedToolName(): void {
		$tool = new CurrentTimeAgentTool('id1');
		$context = new CurrentTimeAgentContextStub();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Unsupported tool: nope');

		$tool->callTool('nope', [], $context);
	}

	public function testCallToolUsesProvidedValidTimezone(): void {
		$tool = new CurrentTimeAgentTool('id1');
		$context = new CurrentTimeAgentContextStub();

		$result = $tool->callTool('get_current_time', ['timezone' => 'Europe/Berlin'], $context);

		$this->assertSame('Europe/Berlin', $result['timezone']);
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result['time']);
	}

	public function testCallToolDefaultsToServerTimezoneWhenTimezoneArgumentMissing(): void {
		$tool = new CurrentTimeAgentTool('id1');
		$context = new CurrentTimeAgentContextStub();

		$serverTz = date_default_timezone_get();
		$result = $tool->callTool('get_current_time', [], $context);

		$this->assertSame($serverTz, $result['timezone']);
	}

	public function testCallToolFallsBackToServerTimezoneOnInvalidTimezone(): void {
		$tool = new CurrentTimeAgentTool('id1');
		$context = new CurrentTimeAgentContextStub();
		$result = $tool->callTool('get_current_time', ['timezone' => 'This/Is-Not-A-Timezone'], $context);

		$this->assertSame(date_default_timezone_get(), $result['timezone']);
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result['time']);
	}

}

require_once __DIR__ . '/CurrentTimeAgentContextStub.php';
require_once __DIR__ . '/CurrentTimeAgentMemoryStub.php';
