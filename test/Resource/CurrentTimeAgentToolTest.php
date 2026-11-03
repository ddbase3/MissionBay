<?php declare(strict_types=1);

namespace MissionBay\Resource\Test;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\AgentTool\CurrentTime\CurrentTimeAgentTool;
use MissionBay\Api\IAgentTool;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentMemory;

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
