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

use Base3\MissionBay\Api\IAgentComponentPresetRepository;
use Base3\MissionBay\Service\AgentComponentPresetFlowExpander;
use PHPUnit\Framework\TestCase;

final class AgentComponentPresetFlowExpanderTest extends TestCase {

	public function testMissingDisabledAndInvalidPresetsProduceWarnings(): void {
		$expander = $this->expander([
			'disabled' => ['type' => 'search', 'enabled' => false],
			'missingtype' => ['enabled' => true]
		]);
		$result = $expander->expand([], ['missing', 'disabled', 'missingtype', '']);
		$this->assertSame([], $result['flow']['resources']);
		$this->assertCount(3, $result['warnings']);
		$this->assertStringContainsString('not found', $result['warnings'][0]);
		$this->assertStringContainsString('disabled', $result['warnings'][1]);
		$this->assertStringContainsString('no type', $result['warnings'][2]);
		$this->assertSame([], $result['flow']['nodes']);
		$this->assertSame([], $result['flow']['connections']);
	}

	public function testRecursiveDocksMaterializeReferencedPresetsOnce(): void {
		$expander = $this->expander([
			'root' => ['type' => 'root_type', 'config' => ['key' => 'value'], 'docks' => ['source' => ['child', 'child', 'external-id']]],
			'child' => ['type' => 'child_type']
		]);
		$result = $expander->expand([], ['root', 'child']);
		$this->assertCount(2, $result['flow']['resources']);
		$this->assertSame(['preset_child', 'external-id'], $result['flow']['resources'][1]['docks']['source']);
		$this->assertSame(['key' => 'value'], $result['flow']['resources'][1]['config']);
		$this->assertSame(['root' => 'preset_root', 'child' => 'preset_child'], $result['resource_ids']);
		$this->assertSame([], $result['warnings']);
	}

	public function testCircularDockReferencesAreReportedWithoutInfiniteRecursion(): void {
		$result = $this->expander([
			'a' => ['type' => 'alpha', 'docks' => ['next' => ['b']]],
			'b' => ['type' => 'beta', 'docks' => ['back' => ['a']]]
		])->expand([], ['a']);
		$this->assertCount(2, $result['flow']['resources']);
		$this->assertStringContainsString('Circular', $result['warnings'][0]);
		$this->assertSame('preset_a', $result['resource_ids']['a']);
	}

	public function testExistingResourceIsNotDuplicated(): void {
		$result = $this->expander(['a' => ['type' => 'new']])->expand(['resources' => [['id' => 'preset_a', 'type' => 'existing']]], ['a']);
		$this->assertCount(1, $result['flow']['resources']);
		$this->assertSame('existing', $result['flow']['resources'][0]['type']);
	}

	public function testResourceIdsUseStableSanitizedTechnicalNames(): void {
		$this->assertSame('preset_my_special', AgentComponentPresetFlowExpander::getResourceIdForPreset('My Special!'));
		$this->assertSame('preset_a_b', AgentComponentPresetFlowExpander::getResourceIdForPreset('a+b'));
	}

	private function expander(array $presets): AgentComponentPresetFlowExpander {
		$repository = $this->createStub(IAgentComponentPresetRepository::class);
		$repository->method('getPreset')->willReturnCallback(static fn(string $id, array $default = []): array => $presets[$id] ?? $default);
		return new AgentComponentPresetFlowExpander($repository);
	}
}
