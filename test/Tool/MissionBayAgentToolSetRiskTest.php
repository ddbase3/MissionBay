<?php declare(strict_types=1);

namespace MissionBay\Test\Tool;

use MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use MissionBay\Tool\Profile\MissionBayAgentToolSet;
use PHPUnit\Framework\TestCase;

final class MissionBayAgentToolSetRiskTest extends TestCase {

	public function testExplicitRiskHintOverridesDestructiveFallback(): void {
		$reflection = new \ReflectionClass(MissionBayAgentToolSet::class);
		$toolSet = $reflection->newInstanceWithoutConstructor();
		$property = $reflection->getProperty('definitionSemantics');
		$property->setValue($toolSet, new AgentToolDefinitionSemantics());
		$method = $reflection->getMethod('readRisk');

		$this->assertSame('high', $method->invoke($toolSet, [
			'annotations' => [
				'riskHint' => 'high',
				'destructiveHint' => false
			],
			'function' => ['name' => 'remote_tool']
		]));
	}
}
