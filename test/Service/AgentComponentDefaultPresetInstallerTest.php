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
use Base3\MissionBay\Service\AgentComponentDefaultPresetInstaller;
use PHPUnit\Framework\TestCase;

final class AgentComponentDefaultPresetInstallerTest extends TestCase {

	public function testInstallsBothDefaultsWhenNotPresent(): void {
		$saved = [];
		$repository = $this->createStub(IAgentComponentPresetRepository::class);
		$repository->method('hasPreset')->willReturn(false);
		$repository->method('savePreset')->willReturnCallback(static function(string $id, array $preset) use (&$saved): void { $saved[$id] = $preset; });
		$installer = new AgentComponentDefaultPresetInstaller($repository);
		$result = $installer->installDefaults();
		$this->assertTrue($result['ok']);
		$this->assertSame(2, $result['count_installed']);
		$this->assertSame(0, $result['count_skipped']);
		$this->assertSame(['timememory_default', 'webfetch_default'], $result['installed']);
		$this->assertSame(['memory'], $saved['timememory_default']['capabilities']);
		$this->assertSame(['tool'], $saved['webfetch_default']['capabilities']);
	}

	public function testNonOverwritingInstallSkipsExistingPreset(): void {
		$saved = [];
		$repository = $this->createStub(IAgentComponentPresetRepository::class);
		$repository->method('hasPreset')->willReturnCallback(static fn(string $id): bool => $id === 'webfetch_default');
		$repository->method('savePreset')->willReturnCallback(static function(string $id, array $preset) use (&$saved): void { $saved[$id] = $preset; });
		$result = (new AgentComponentDefaultPresetInstaller($repository))->installDefaults(false);
		$this->assertSame(['webfetch_default'], $result['skipped']);
		$this->assertSame(['timememory_default'], array_keys($saved));
		$this->assertSame(1, $result['count_installed']);
	}

	public function testOverwriteInstallsExistingPresets(): void {
		$saved = [];
		$repository = $this->createStub(IAgentComponentPresetRepository::class);
		$repository->method('hasPreset')->willReturn(true);
		$repository->method('savePreset')->willReturnCallback(static function(string $id, array $preset) use (&$saved): void { $saved[$id] = $preset; });
		$result = (new AgentComponentDefaultPresetInstaller($repository))->installDefaults(true);
		$this->assertTrue($result['overwrite']);
		$this->assertSame([], $result['skipped']);
		$this->assertCount(2, $saved);
	}

	public function testDefaultComponentsReferenceExistingDefaultPresets(): void {
		$installer = new AgentComponentDefaultPresetInstaller($this->createStub(IAgentComponentPresetRepository::class));
		$presets = $installer->getDefaultPresets();
		$components = $installer->getDefaultAgentComponents();
		$this->assertCount(2, $presets);
		$this->assertCount(2, $components);
		foreach ($components as $component) {
			$this->assertArrayHasKey($component['preset'], $presets);
			$this->assertTrue($component['enabled']);
		}
	}
}
