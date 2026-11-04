<?php declare(strict_types=1);

namespace MissionBay\Test\Agent;

use Base3\Api\IClassMap;
use MissionBay\Agent\AgentResourceFactory;
use MissionBay\Api\IAgentResource;
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
		$map->expects($this->once())->method('getClassByInterfaceName')->with(IAgentResource::class, 'memory')->willReturn('MissionBay\\Resource\\Memory');
		$map->expects($this->once())->method('instantiate')->with('MissionBay\\Resource\\Memory')->willReturn($resource);
		$this->assertSame($resource, (new AgentResourceFactory($map))->createResource('memory'));
	}

	public function testUnrelatedInstanceDoesNotEscapeAsAgentResource(): void {
		$map = $this->createStub(IClassMap::class);
		$map->method('getClassByInterfaceName')->willReturn('Unknown\\Class');
		$map->method('instantiate')->willReturn(new \stdClass());
		$this->assertNull((new AgentResourceFactory($map))->createResource('type'));
	}
}
