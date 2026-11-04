<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Service\AgentComponentDefaultPresetInstaller;
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
