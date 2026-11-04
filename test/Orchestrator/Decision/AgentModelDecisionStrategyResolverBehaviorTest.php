<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator\Decision;

use Base3\Api\IClassMap;
use MissionBay\Api\IAgentModelDecisionStrategy;
use MissionBay\Orchestrator\Decision\AgentModelDecisionStrategyResolver;
use PHPUnit\Framework\TestCase;

final class AgentModelDecisionStrategyResolverBehaviorTest extends TestCase {

	public function testBlankStrategyNameFailsBeforeClassMapLookup(): void {
		$map = $this->createMock(IClassMap::class);
		$map->expects($this->never())->method('getInstanceByInterfaceName');
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('must not be empty');
		(new AgentModelDecisionStrategyResolver($map))->resolve(" \t ");
	}

	public function testUnknownStrategyIsNotSilentlySubstituted(): void {
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstanceByInterfaceName')->willReturn(null);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('could not be resolved: unknown');
		(new AgentModelDecisionStrategyResolver($map))->resolve(' unknown ');
	}

	public function testNonStrategyClassMapResultIsRejected(): void {
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstanceByInterfaceName')->willReturn(new \stdClass());
		$this->expectException(\RuntimeException::class);
		(new AgentModelDecisionStrategyResolver($map))->resolve('custom');
	}

	public function testResolvedStrategyIsReturnedWithoutReplacement(): void {
		$strategy = $this->createStub(IAgentModelDecisionStrategy::class);
		$map = $this->createMock(IClassMap::class);
		$map->expects($this->once())->method('getInstanceByInterfaceName')
			->with(IAgentModelDecisionStrategy::class, 'native')->willReturn($strategy);
		$this->assertSame($strategy, (new AgentModelDecisionStrategyResolver($map))->resolve(' native '));
	}
}
