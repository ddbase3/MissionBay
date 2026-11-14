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

namespace Base3\MissionBay\Test\Agent;

use Base3\CoreFoundation\Api\IClassMap;
use Base3\MissionBay\Agent\AgentResourceFactory;
use Base3\MissionBay\Api\IAgentResource;
use PHPUnit\Framework\TestCase;

final class AgentResourceFactoryTest extends TestCase {

	public function testUnknownResourceTypeReturnsNullWithoutInstantiation(): void {
		$map = $this->createMock(IClassMap::class);
		$map->expects($this->once())->method('getClassByInterfaceName')->with(IAgentResource::class, 'unknown')->willReturn(null);
		$map->expects($this->never())->method('instantiate');
		$this->assertNull((new AgentResourceFactory($map))->createResource('unknown'));
	}

	public function testResolvedResourceClassUsesFreshInstantiation(): void {
		$resource = $this->createStub(IAgentResource::class);
		$map = $this->createMock(IClassMap::class);
		$map->expects($this->once())->method('getClassByInterfaceName')->with(IAgentResource::class, 'memory')->willReturn('Base3\\MissionBay\\Resource\\Memory');
		$map->expects($this->once())->method('instantiate')->with('Base3\\MissionBay\\Resource\\Memory')->willReturn($resource);
		$this->assertSame($resource, (new AgentResourceFactory($map))->createResource('memory'));
	}

	public function testUnrelatedInstanceDoesNotEscapeAsAgentResource(): void {
		$map = $this->createStub(IClassMap::class);
		$map->method('getClassByInterfaceName')->willReturn('Unknown\\Class');
		$map->method('instantiate')->willReturn(new \stdClass());
		$this->assertNull((new AgentResourceFactory($map))->createResource('type'));
	}
}
