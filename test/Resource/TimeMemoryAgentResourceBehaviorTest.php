<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentInstructionBlock;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Resource\AgentMemory\Time\TimeMemoryAgentResource;
use PHPUnit\Framework\TestCase;

final class TimeMemoryAgentResourceBehaviorTest extends TestCase {

	public function testContributesOneTypedTimeBlockWithMetadata(): void {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$resource = new TimeMemoryAgentResource($resolver, 'clock-1');
		$blocks = [...$resource->contribute($this->unusedContext())];
		$this->assertCount(1, $blocks);
		$this->assertInstanceOf(AgentInstructionBlock::class, $blocks[0]);
		$this->assertSame('current-time', $blocks[0]->getId());
		$this->assertSame('clock-1', $blocks[0]->getSource());
		$this->assertSame(['implementation' => TimeMemoryAgentResource::getName()], $blocks[0]->getMetadata());
		$this->assertMatchesRegularExpression('/^Current time is .+ \(weekday: [A-Za-z]+\)$/', $blocks[0]->getContent());
	}

	public function testDefaultPriorityAndConfigArePreserved(): void {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$resource = new TimeMemoryAgentResource($resolver, 'time');
		$this->assertSame(10, $resource->getPriority());
		$this->assertSame([], $resource->getConfig());
		$this->assertSame([], $resource->getDockDefinitions());
	}

	public function testPriorityUsesResolvedConfiguredValue(): void {
		$resolver = $this->createMock(IAgentConfigValueResolver::class);
		$resolver->expects($this->once())->method('resolveValue')->with(['mode' => 'fixed', 'value' => 42])->willReturn(42);
		$resource = new TimeMemoryAgentResource($resolver);
		$config = ['priority' => ['mode' => 'fixed', 'value' => 42]];
		$resource->setConfig($config);
		$this->assertSame(42, $resource->getPriority());
		$this->assertSame($config, $resource->getConfig());
	}

	public function testMissingResolvedPriorityUsesDefault(): void {
		$resolver = $this->createStub(IAgentConfigValueResolver::class);
		$resolver->method('resolveValue')->willReturn(null);
		$resource = new TimeMemoryAgentResource($resolver);
		$resource->setConfig([]);
		$this->assertSame(10, $resource->getPriority());
	}

	private function unusedContext(): IAgentContext {
		return $this->createStub(IAgentContext::class);
	}
}
