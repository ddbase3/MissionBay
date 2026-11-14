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

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\MissionBay\Agent\AgentContextFactory;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Memory\NoMemory;
use PHPUnit\Framework\TestCase;

final class AgentContextFactoryTest extends TestCase {

	public function testFactoryAssignsMemoryAndVariables(): void {
		$context = new AgentContext();
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstanceByInterfaceName')->willReturn($context);
		$memory = new NoMemory();
		$result = (new AgentContextFactory($map))->createContext('agentcontext', $memory, ['a' => 1, 'b' => null]);
		$this->assertSame($context, $result);
		$this->assertSame($memory, $result->getMemory());
		$this->assertSame(['a', 'b'], $result->listVars());
		$this->assertSame(1, $result->getVar('a'));
	}

	public function testMissingContextTypeFailsInsteadOfCreatingAnUnconfiguredContext(): void {
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstanceByInterfaceName')->willReturn(null);
		$this->expectException(\RuntimeException::class);
		(new AgentContextFactory($map))->createContext('missing');
	}

	public function testNonContextInstanceIsRejected(): void {
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstanceByInterfaceName')->willReturn(new \stdClass());
		$this->expectException(\RuntimeException::class);
		(new AgentContextFactory($map))->createContext('incorrect');
	}
}
