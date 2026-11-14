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

use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Service\AgentComponentPresetRepository;
use PHPUnit\Framework\TestCase;

final class AgentComponentPresetRepositoryTest extends TestCase {

	public function testValidPresetsAreSortedAndReceiveTheirIdentifiers(): void {
		$records = [
			'zeta' => ['label' => 'Last'],
			'alpha' => ['id' => 'custom', 'label' => 'First'],
			'bad' => null,
			5 => ['label' => 'invalid key']
		];
		$repository = $this->repository($records);
		$this->assertSame([
			'alpha' => ['id' => 'custom', 'label' => 'First'],
			'zeta' => ['label' => 'Last', 'id' => 'zeta']
		], $repository->getPresets());
	}

	public function testMissingPresetUsesCallerDefaultAndExistingPresetGetsId(): void {
		$records = ['exists' => ['type' => 'memory']];
		$repository = $this->repository($records);
		$this->assertSame(['type' => 'memory', 'id' => 'exists'], $repository->getPreset('exists'));
		$this->assertSame(['not_found' => true], $repository->getPreset('missing', ['not_found' => true]));
		$this->assertFalse($repository->hasPreset('missing'));
		$this->assertTrue($repository->hasPreset('exists'));
	}

	public function testSavingPreservesCallerIdOrSuppliesRequestedIdAndPersists(): void {
		$records = [];
		$calls = [];
		$repository = $this->repository($records, $calls);
		$repository->savePreset('one', ['type' => 'tool']);
		$repository->savePreset('two', ['id' => 'original', 'enabled' => false]);
		$this->assertSame(['type' => 'tool', 'id' => 'one'], $records['one']);
		$this->assertSame(['id' => 'original', 'enabled' => false], $records['two']);
		$this->assertSame([
			['set', 'agent-component-preset', 'one'], ['save'],
			['set', 'agent-component-preset', 'two'], ['save']
		], $calls);
	}

	public function testRemovingPresetDoesNotTouchOtherEntries(): void {
		$records = ['first' => ['type' => 'a'], 'second' => ['type' => 'b']];
		$calls = [];
		$repository = $this->repository($records, $calls);
		$repository->removePreset('first');
		$this->assertArrayNotHasKey('first', $records);
		$this->assertArrayHasKey('second', $records);
		$this->assertSame([['remove', 'agent-component-preset', 'first'], ['save']], $calls);
	}

	/** @param array<int|string,mixed> $records @param array<int,array<int,string>> $calls */
	private function repository(array &$records, array &$calls = []): AgentComponentPresetRepository {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('getGroup')->willReturnCallback(static function(string $group) use (&$records): array { return $records; });
		$settings->method('get')->willReturnCallback(static function(string $group, string $id, array $default = []) use (&$records): array {
			return $records[$id] ?? $default;
		});
		$settings->method('has')->willReturnCallback(static function(string $group, string $id) use (&$records): bool { return array_key_exists($id, $records); });
		$settings->method('set')->willReturnCallback(static function(string $group, string $id, array $preset) use (&$records, &$calls): void {
			$calls[] = ['set', $group, $id];
			$records[$id] = $preset;
		});
		$settings->method('remove')->willReturnCallback(static function(string $group, string $id) use (&$records, &$calls): void {
			$calls[] = ['remove', $group, $id];
			unset($records[$id]);
		});
		$settings->method('save')->willReturnCallback(static function() use (&$calls): void { $calls[] = ['save']; });
		return new AgentComponentPresetRepository($settings);
	}
}
