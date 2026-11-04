<?php declare(strict_types=1);

namespace MissionBay\Test\Agent;

use AssistantFoundation\Api\IAgentContext;
use Base3\Api\IClassMap;
use MissionBay\Agent\AgentContextFactory;
use MissionBay\Context\AgentContext;
use MissionBay\Memory\NoMemory;
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
