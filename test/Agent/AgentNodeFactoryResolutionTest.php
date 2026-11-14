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
use Base3\MissionBay\Agent\AgentNodeFactory;
use Base3\MissionBay\Api\IAgentNode;
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
