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

namespace Base3\MissionBay\Test\Orchestrator\Decision;

use Base3\CoreFoundation\Api\IClassMap;
use Base3\MissionBay\Api\IAgentModelDecisionStrategy;
use Base3\MissionBay\Orchestrator\Decision\AgentModelDecisionStrategyResolver;
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
