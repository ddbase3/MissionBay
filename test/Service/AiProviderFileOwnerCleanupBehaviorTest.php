<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use AssistantFoundation\Dto\AiProviderFileReference;
use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use Base3\State\Api\IStateStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Service\AiProviderFileOwnerCleanupService;
use MissionBay\Service\AiProviderFileReferenceStateRepository;
use MissionBay\Service\ConfiguredServiceRuntimeResolver;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IManagedFileStorageService;
use ResourceFoundation\Event\ManagedFileStorageDeletingEvent;

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
