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

namespace Base3\MissionBay\Test\Resource;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Dto\AgentInstructionBlock;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Resource\AgentMemory\Time\TimeMemoryAgentResource;
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
