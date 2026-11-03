<?php declare(strict_types=1);

namespace MissionBay\Test\Profile;

use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Profile\AgentContextProfileResolver;
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
