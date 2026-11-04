<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use Base3\Api\IClassMap;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Service\AgentComponentPresetCatalog;
use PHPUnit\Framework\TestCase;

final class AgentComponentPresetCatalogTest extends TestCase {

	public function testOptionsAreEnabledFilteredAndAlphabeticallySorted(): void {
		$presets = [
			'zz' => ['type' => 'tool', 'label' => 'Zulu'],
			'aa' => ['type' => 'tool', 'name' => 'Alpha'],
			'off' => ['type' => 'tool', 'enabled' => 'off'],
			'no-type' => ['label' => 'no type'],
			'   ' => ['type' => 'tool']
		];
		$catalog = $this->catalog($presets);
		$this->assertSame([
			['id' => 'aa', 'label' => 'Alpha', 'type' => 'tool'],
			['id' => 'zz', 'label' => 'Zulu', 'type' => 'tool']
		], $catalog->getPresetOptionsByInterface('ToolInterface'));
	}

	public function testEnabledAcceptsBooleanIntegerAndStringForms(): void {
		$presets = [];
		foreach (['default' => [], 'yes' => ['enabled' => 'YES'], 'one' => ['enabled' => 1], 'zero' => ['enabled' => 0], 'no' => ['enabled' => 'no'], 'false' => ['enabled' => false], 'true' => ['enabled' => true]] as $id => $config) {
			$presets[$id] = ['type' => 'tool'] + $config;
		}
		$ids = array_column($this->catalog($presets)->getPresetOptionsByInterface('ToolInterface'), 'id');
		$this->assertSame(['default', 'one', 'true', 'yes'], $ids);
	}

	public function testMissingDisabledAndUnknownTypesDoNotImplementInterface(): void {
		$catalog = $this->catalog([
			'active' => ['type' => 'tool'],
			'disabled' => ['type' => 'tool', 'enabled' => false],
			'unknown' => ['type' => 'unmatched'],
			'blank' => ['type' => '']
		]);
		$this->assertTrue($catalog->presetImplements(' active ', 'ToolInterface'));
		foreach (['missing', 'disabled', 'unknown', 'blank'] as $id) {
			$this->assertFalse($catalog->presetImplements($id, 'ToolInterface'));
		}
	}

	/** @param array<string,array<string,mixed>> $presets */
	private function catalog(array $presets): AgentComponentPresetCatalog {
		$repository = $this->createStub(IAgentComponentPresetRepository::class);
		$repository->method('getPresets')->willReturn($presets);
		$repository->method('getPreset')->willReturnCallback(static fn(string $id, array $default = []): array => $presets[$id] ?? $default);
		$map = $this->createStub(IClassMap::class);
		$map->method('getClassByInterfaceName')->willReturnCallback(static fn(string $interface, string $name): ?string => $name === 'tool' ? 'SomeTool' : null);
		return new AgentComponentPresetCatalog($repository, $map);
	}
}
