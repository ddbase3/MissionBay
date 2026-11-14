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

namespace Base3\MissionBay\Test\Service;

use Base3\MissionBay\Api\IAgentComponentPresetFlowExpander;
use Base3\MissionBay\Api\IAgentComponentPresetRepository;
use Base3\MissionBay\Service\AgentComponentFlowBuilder;
use PHPUnit\Framework\TestCase;

final class AgentComponentFlowBuilderBehaviorTest extends TestCase {

	public function testMalformedFlowIsNormalizedWithoutConstructingMissingAssistant(): void {
		$builder = $this->builder(['model' => ['enabled' => true]]);
		$flow = $builder->build(['nodes' => false, 'resources' => null], [['preset' => 'model']]);
		$this->assertSame([], $flow['nodes']);
		$this->assertSame([], $flow['resources']);
		$this->assertSame([], $flow['connections']);
		$this->assertSame(['Assistant node not found: assistant'], $builder->getWarnings());
	}

	public function testTypeBasedAssistantFallbackIsUsedForCustomNodeId(): void {
		$builder = $this->builder(['model' => ['capabilities' => ['chatmodel']]]);
		$flow = $builder->build([
			'nodes' => [['id' => 'renamed', 'type' => 'aiassistantnode']],
			'resources' => [], 'connections' => []
		], [['preset' => 'model']]);
		$this->assertSame(['preset_model'], $flow['nodes'][0]['docks']['chatmodel']);
	}

	public function testDisabledOrUnknownComponentsAreNotMaterialized(): void {
		$builder = $this->builder(['disabled' => ['enabled' => 'off', 'capabilities' => ['tool']]]);
		$flow = $builder->build($this->base(), [
			false, [], ['preset' => 'missing'],
			['preset' => 'disabled'], ['preset' => 'disabled', 'enabled' => 'OFF']
		]);
		$this->assertSame([], $flow['resources']);
		$this->assertSame([], $flow['nodes'][0]['docks'] ?? []);
		$this->assertSame([
				'Component entry is not an array at index 0.',
				'Component has no preset at index 1.',
				'Component preset not found: missing',
				'Component preset is disabled: disabled'
			], $builder->getWarnings());
	}

	public function testAttachAsStringIsNormalizedAndDeduplicated(): void {
		$builder = $this->builder(['multi' => ['capabilities' => ['context']]]);
		$flow = $builder->build($this->base(), [[
			'preset' => 'multi', 'attach_as' => ' chatmodel, CONTEXT, chatmodel, missing '
		]]);
		$this->assertSame(['preset_multi'], $flow['nodes'][0]['docks']['chatmodel']);
		$this->assertSame(['preset_multi'], $flow['nodes'][0]['docks']['contextcontributors']);
		$this->assertCount(0, array_filter($flow['resources'], static fn(array $item): bool => str_starts_with($item['type'] ?? '', 'configuredagent')));
	}

	public function testToolAndMemoryWrappersApplyTheirOwnConfiguration(): void {
		$builder = $this->builder(['component' => ['capabilities' => ['tool', 'memory']]]);
		$flow = $builder->build($this->base(), [[
			'preset' => 'component', 'attach_as' => ['tool', 'memory'],
			'tool_config' => ['timeout' => 12], 'tool_namespace' => 'explicit',
			'memory_config' => ['priority' => 4], 'order' => 9, 'enabled' => true
		]]);
		$resources = array_column($flow['resources'], null, 'type');
		$tool = $resources['configuredagenttoolresource'];
		$memory = $resources['configuredagentmemoryresource'];
		$this->assertSame('explicit', $tool['config']['namespace']);
		$this->assertSame(12, $tool['config']['timeout']);
		$this->assertTrue($tool['config']['enabled']);
		$this->assertSame(4, $memory['config']['priority']);
		$this->assertTrue($memory['config']['enabled']);
		$this->assertSame(['preset_component'], $tool['docks']['tool']);
		$this->assertSame(['preset_component'], $memory['docks']['memory']);
	}

	public function testMemoryPriorityDefaultsToComponentOrder(): void {
		$builder = $this->builder(['memory-main' => ['capabilities' => ['memory']]]);
		$flow = $builder->build($this->base(), [[
			'preset' => 'memory-main', 'order' => 7, 'memory_config' => 'invalid'
		]]);
		$resources = array_values(array_filter($flow['resources'], static fn(array $item): bool => ($item['type'] ?? '') === 'configuredagentmemoryresource'));
		$this->assertSame(7, $resources[0]['config']['priority']);
		$this->assertSame('configured_memory_memory_main_0', $resources[0]['id']);
	}

	public function testResourceIdCollisionAppendsCounterWithoutOverwritingOriginal(): void {
		$builder = $this->builder(['tool' => ['capabilities' => ['tool']]]);
		$base = $this->base();
		$base['resources'][] = ['id' => 'configured_tool_tool_0', 'type' => 'existing'];
		$flow = $builder->build($base, [['preset' => 'tool']]);
		$this->assertSame('existing', $flow['resources'][0]['type']);
		$this->assertSame('configured_tool_tool_0_2', $flow['nodes'][0]['docks']['tools'][0]);
	}

	public function testExpansionWarningsAndMissingResourceIdArePreserved(): void {
		$builder = $this->builder(['item' => ['capabilities' => ['context']]], false);
		$flow = $builder->build($this->base(), [['preset' => 'item']]);
		$this->assertSame(['preset_item'], $flow['nodes'][0]['docks']['contextcontributors']);
		$this->assertSame(['Unresolved optional dependency', 'Component preset could not be expanded: item'], $builder->getWarnings());
	}

	public function testWarningsAreResetBetweenBuildCalls(): void {
		$builder = $this->builder(['item' => ['capabilities' => ['context']]]);
		$builder->build([], [['preset' => 'item']]);
		$this->assertNotEmpty($builder->getWarnings());
		$builder->build($this->base(), []);
		$this->assertSame([], $builder->getWarnings());
	}

	/** @param array<string,array<string,mixed>> $presets */
	private function builder(array $presets, bool $expand = true): AgentComponentFlowBuilder {
		$repository = $this->createStub(IAgentComponentPresetRepository::class);
		$repository->method('getPreset')->willReturnCallback(static fn(string $id, array $default = []): array => $presets[$id] ?? $default);
		$expander = $this->createStub(IAgentComponentPresetFlowExpander::class);
		$expander->method('expand')->willReturnCallback(static function(array $flow, array $ids) use ($expand): array {
			$id = $ids[0];
			return ['flow' => $flow, 'resource_ids' => $expand ? [$id => 'preset_' . str_replace('-', '_', $id)] : [],
				'warnings' => $expand ? [] : ['Unresolved optional dependency']];
		});
		return new AgentComponentFlowBuilder($repository, $expander);
	}

	private function base(): array {
		return ['nodes' => [['id' => 'assistant', 'type' => 'aiassistantnode']], 'resources' => [], 'connections' => []];
	}
}
