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

namespace Base3\MissionBay\Test\Profile;

use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IAgentComponentPresetRepository;
use Base3\MissionBay\Api\IAgentResourceFactory;
use Base3\MissionBay\Profile\AgentContextProfileResolver;
use PHPUnit\Framework\TestCase;

final class AgentContextProfileResolverTest extends TestCase {

	public function testNormalizationFiltersAndDeduplicatesProfilePresetIdentifiers(): void {
		$resolver = $this->resolver([]);
		$this->assertSame([
			'id' => 'myprofile',
			'label' => 'myprofile',
			'description' => 'Describe',
			'enabled' => true,
			'contexts' => ['page', 'other']
		], $resolver->normalizeProfile(' My Profile ', ['description' => ' Describe ', 'contexts' => ' Page, page,  other , !!!']));
		$this->assertSame([], $resolver->normalizeProfile('example', ['contexts' => new \stdClass()])['contexts']);
		$this->assertFalse($resolver->normalizeProfile('example', ['enabled' => 'off'])['enabled']);
	}

	public function testOptionsAreSortedAndDisabledEntriesExcluded(): void {
		$resolver = $this->resolver([
			'z' => ['label' => 'Zed', 'contexts' => ['a']],
			'a' => ['label' => 'Alpha', 'contexts' => []],
			'off' => ['label' => 'Disabled', 'enabled' => false, 'contexts' => ['a']],
			'invalid' => 'bad'
		]);
		$this->assertSame(['a', 'z'], array_column($resolver->getOptions(), 'id'));
		$this->assertSame([0, 1], array_column($resolver->getOptions(), 'context_count'));
		$this->assertTrue($resolver->hasProfile('z'));
		$this->assertFalse($resolver->hasProfile('a'));
		$this->assertFalse($resolver->hasProfile('unknown'));
	}

	public function testUnknownAndEmptyProfileIdsAreRejected(): void {
		$resolver = $this->resolver([]);
		foreach (['', 'missing'] as $id) {
			try {
				$resolver->getProfile($id);
				$this->fail('Invalid context profile must fail.');
			}
			catch (\InvalidArgumentException|\RuntimeException $exception) {
				$this->assertNotEmpty($exception->getMessage());
			}
		}
		$this->assertSame([], $resolver->resolveComponents(''));
	}

	public function testDisabledProfileCannotBeResolved(): void {
		$resolver = $this->resolver(['disabled' => ['enabled' => false, 'contexts' => ['test']]]);
		try {
			$resolver->resolveComponents('disabled');
			$this->fail('Disabled context profile must not materialize.');
		}
		catch (\RuntimeException $exception) {
			$this->assertStringContainsString('disabled', $exception->getMessage());
		}
	}

	public function testUnknownOrDisabledPresetsAreRejectedAtResolutionBoundary(): void {
		foreach ([[], ['context' => ['type' => 'valid', 'enabled' => false]]] as $presets) {
			$resolver = $this->resolver(['default' => ['contexts' => ['context']]], $presets);
			try {
				$resolver->resolveComponents('default');
				$this->fail('Unavailable context preset must fail.');
			}
			catch (\RuntimeException $exception) {
				$this->assertNotEmpty($exception->getMessage());
			}
		}
	}

	private function resolver(array $profiles, array $presets = []): AgentContextProfileResolver {
		$store = $this->createStub(ISettingsStore::class);
		$store->method('getGroup')->willReturn($profiles);
		$store->method('get')->willReturnCallback(static fn(string $group, string $id, array $default = []): array => $profiles[$id] ?? $default);
		$repository = $this->createStub(IAgentComponentPresetRepository::class);
		$repository->method('getPreset')->willReturnCallback(static fn(string $id, array $default = []): array => $presets[$id] ?? $default);
		return new AgentContextProfileResolver($store, $repository, $this->createStub(IAgentResourceFactory::class));
	}
}
