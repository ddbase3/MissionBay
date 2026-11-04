<?php declare(strict_types=1);

namespace MissionBay\Test\Agent;

use Base3\Api\IClassMap;
use MissionBay\Agent\AgentFlowFactory;
use MissionBay\Api\IAgentFlow;
use MissionBay\Api\IAgentNodeFactory;
use MissionBay\Context\AgentContext;
use PHPUnit\Framework\TestCase;

final class AgentFlowFactoryTest extends TestCase {

	public function testCreatesEmptyFlowWithExplicitContext(): void {
		$context = new AgentContext();
		$flow = $this->createMock(IAgentFlow::class);
		$flow->expects($this->once())->method('setContext')->with($context);
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstanceByInterfaceName')->willReturn($flow);
		$factory = new AgentFlowFactory($map, $this->createStub(IAgentNodeFactory::class));
		$this->assertSame($flow, $factory->createEmpty('dynamic', $context));
	}

	public function testCreatesEmptyFlowWithoutContextMutation(): void {
		$flow = $this->createMock(IAgentFlow::class);
		$flow->expects($this->never())->method('setContext');
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstanceByInterfaceName')->willReturn($flow);
		$this->assertSame($flow, (new AgentFlowFactory($map, $this->createStub(IAgentNodeFactory::class)))->createEmpty('dynamic'));
	}

	public function testUnknownFlowTypeIsRejected(): void {
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstanceByInterfaceName')->willReturn(null);
		$factory = new AgentFlowFactory($map, $this->createStub(IAgentNodeFactory::class));
		$this->expectException(\RuntimeException::class);
		$factory->createEmpty('missing');
	}
}
