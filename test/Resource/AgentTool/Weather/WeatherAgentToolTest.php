<?php declare(strict_types=1);

namespace MissionBay\Test\Resource\AgentTool\Weather;

use MissionBay\Context\AgentContext;
use MissionBay\Resource\AgentTool\Weather\WeatherAgentTool;
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
