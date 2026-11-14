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

use Base3\AssistantFoundation\Dto\AiProviderFileReference;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\CoreFoundation\State\Api\IStateStore;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Service\AiProviderFileOwnerCleanupService;
use Base3\MissionBay\Service\AiProviderFileReferenceStateRepository;
use Base3\MissionBay\Service\ConfiguredServiceRuntimeResolver;
use PHPUnit\Framework\TestCase;
use Base3\ResourceFoundation\Api\IManagedFileStorageService;
use Base3\ResourceFoundation\Event\ManagedFileStorageDeletingEvent;

final class AiProviderFileOwnerCleanupBehaviorTest extends TestCase {

	public function testNonCollectionDeletionDoesNotRemoveRegisteredProviderFiles(): void {
		$values = [];
		$repo = new AiProviderFileReferenceStateRepository($this->stateStore($values));
		$repo->set('model', 'owner', 'alice', 'source.pdf', $this->reference());
		$service = $this->service($repo, $this->createStub(ISettingsStore::class));
		$service->handle(new ManagedFileStorageDeletingEvent('owner', 'alice', 'file'));
		$this->assertCount(1, $repo->list('model', 'owner', 'alice'));
		$this->assertSame(['model'], $repo->listRegisteredServiceIds('owner', 'alice'));
	}

	public function testOwnerWithNoReferencesHasIndexCleared(): void {
		$values = [];
		$repo = new AiProviderFileReferenceStateRepository($this->stateStore($values));
		$repo->registerService('model', 'owner', 'alice');
		$service = $this->service($repo, $this->createStub(ISettingsStore::class));
		$service->cleanup('owner', 'alice');
		$this->assertSame([], $repo->listRegisteredServiceIds('owner', 'alice'));
	}

	public function testMissingServiceConfigurationPreventsRemoteDeletionAndRetainsReference(): void {
		$values = [];
		$repo = new AiProviderFileReferenceStateRepository($this->stateStore($values));
		$repo->set('gone', 'uploads', 'alice', 'document.txt', $this->reference());
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('getGroup')->willReturn([]);
		$settings->method('get')->willReturn([]);
		$service = $this->service($repo, $settings);
		try {
			$service->cleanup('uploads', 'alice');
			$this->fail('The absent provider must not be ignored.');
		} catch (\RuntimeException $exception) {
			$this->assertStringContainsString('no longer exists: gone', $exception->getMessage());
		}
		$this->assertCount(1, $repo->list('gone', 'uploads', 'alice'));
		$this->assertSame(['gone'], $repo->listRegisteredServiceIds('uploads', 'alice'));
	}

	public function testCleanupDoesNotTouchOtherOwnerIndex(): void {
		$values = [];
		$repo = new AiProviderFileReferenceStateRepository($this->stateStore($values));
		$repo->registerService('model', 'owner', 'alice');
		$repo->registerService('model', 'owner', 'bob');
		$this->service($repo, $this->createStub(ISettingsStore::class))->cleanup('owner', 'alice');
		$this->assertSame([], $repo->listRegisteredServiceIds('owner', 'alice'));
		$this->assertSame(['model'], $repo->listRegisteredServiceIds('owner', 'bob'));
	}

	private function reference(): AiProviderFileReference {
		return new AiProviderFileReference('provider', 'file-1', 'hash-1', 'source.pdf', 'application/pdf', 20);
	}

	private function service(AiProviderFileReferenceStateRepository $repo, ISettingsStore $settings): AiProviderFileOwnerCleanupService {
		return new AiProviderFileOwnerCleanupService($repo,
			new ConfiguredServiceRuntimeResolver($settings, $this->createStub(IClassMap::class), $this->createStub(IAgentConfigValueResolver::class)),
			$settings);
	}

	/** @param array<string,mixed> $values */
	private function stateStore(array &$values): IStateStore {
		$store = $this->createStub(IStateStore::class);
		$store->method('get')->willReturnCallback(static function(string $key, mixed $default = null) use (&$values): mixed { return $values[$key] ?? $default; });
		$store->method('set')->willReturnCallback(static function(string $key, mixed $value, ?int $ttlSeconds = null) use (&$values): void { $values[$key] = $value; });
		$store->method('delete')->willReturnCallback(static function(string $key) use (&$values): bool {
			$exists = array_key_exists($key, $values);
			unset($values[$key]);
			return $exists;
		});
		$store->method('listKeys')->willReturnCallback(static function(string $prefix) use (&$values): array { return array_values(array_filter(array_keys($values), static fn(string $key): bool => str_starts_with($key, $prefix))); });
		return $store;
	}
}
