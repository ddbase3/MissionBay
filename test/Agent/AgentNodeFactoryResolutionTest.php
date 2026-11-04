<?php declare(strict_types=1);

namespace MissionBay\Test\Agent;

use Base3\Api\IClassMap;
use MissionBay\Agent\AgentNodeFactory;
use MissionBay\Api\IAgentNode;
use PHPUnit\Framework\TestCase;

final class AgentNodeFactoryResolutionTest extends TestCase {

	public function testKnownNodeReturnedByClassMap(): void {
		$node = $this->createStub(IAgentNode::class);
		$map = $this->createMock(IClassMap::class);
		$map->expects($this->once())->method('getInstanceByInterfaceName')->with(IAgentNode::class, 'known')->willReturn($node);
		$this->assertSame($node, (new AgentNodeFactory($map))->createNode('known'));
	}

	public function testUnknownNodeReturnsNullWithoutCreatingReplacement(): void {
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstanceByInterfaceName')->willReturn(null);
		$this->assertNull((new AgentNodeFactory($map))->createNode('missing'));
	}

	public function testNonNodeObjectIsRejected(): void {
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstanceByInterfaceName')->willReturn(new \stdClass());
		$this->assertNull((new AgentNodeFactory($map))->createNode('wrong'));
	}
}
