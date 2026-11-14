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

namespace Base3\MissionBay\Test\Resource\AgentTool\Weather;

use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Resource\AgentTool\Weather\WeatherAgentTool;
use PHPUnit\Framework\TestCase;

final class WeatherAgentToolTest extends TestCase {

	public function testPublicWeatherDefinitionIsReadOnlyAndRequiresLocation(): void {
		$tool = new WeatherAgentTool();
		$definition = $tool->getToolDefinitions()[0];
		$this->assertSame('get_weather', $definition['function']['name']);
		$this->assertSame(['location'], $definition['function']['parameters']['required']);
		$this->assertTrue($definition['readOnlyHint']);
		$this->assertFalse($definition['mutation']);
		$this->assertFalse($definition['requiresApproval']);
	}

	public function testMissingLocationDoesNotPerformAnExternalRequest(): void {
		$tool = new WeatherAgentTool();
		$this->assertSame(['error' => 'Missing required parameter: location'], $tool->callTool('get_weather', [], new AgentContext()));
		$this->assertSame(['error' => 'Missing required parameter: location'], $tool->callTool('get_weather', ['location' => '   '], new AgentContext()));
	}

	public function testUnexpectedToolNameIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		(new WeatherAgentTool())->callTool('delete_weather', ['location' => 'Berlin'], new AgentContext());
	}
}
