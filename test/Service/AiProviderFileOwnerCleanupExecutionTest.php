<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use AssistantFoundation\Api\IAiFileCapableChatModel;
use AssistantFoundation\Dto\AiProviderFileReference;
use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use Base3\State\Api\IStateStore;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Service\AiProviderFileOwnerCleanupService;
use MissionBay\Service\AiProviderFileReferenceStateRepository;
use MissionBay\Service\ConfiguredServiceRuntimeResolver;
use MissionBay\ServiceDriver\OpenAiChatServiceDriverDefinition;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IManagedFileStorageService;
use ResourceFoundation\Event\ManagedFileStorageDeletingEvent;

final class AiProviderFileOwnerCleanupExecutionTest extends TestCase {

	public function testCollectionDeletionRemovesRegisteredRemoteFileAndState(): void {
		$records = [];
		$repository = $this->repository($records);
		$repository->set('model', 'uploads', 'alice', 'file.pdf', $this->reference('openai', 'file-1'));
		$deleted = [];
		$model = $this->fileModel($deleted);
		$this->service($repository, $model)->handle(new ManagedFileStorageDeletingEvent('uploads', 'alice', IManagedFileStorageService::MODE_COLLECTION));
		$this->assertSame(['file-1'], $deleted);
		$this->assertSame([], $repository->list('model', 'uploads', 'alice'));
		$this->assertSame([], $repository->listRegisteredServiceIds('uploads', 'alice'));
	}

	public function testMultipleFilesAreDeletedIndividually(): void {
		$records = [];
		$repository = $this->repository($records);
		$repository->set('model', 'uploads', 'alice', 'a.pdf', $this->reference('openai', 'a'));
		$repository->set('model', 'uploads', 'alice', 'b.pdf', $this->reference('openai', 'b'));
		$deleted = [];
		$this->service($repository, $this->fileModel($deleted))->cleanup('uploads', 'alice');
		sort($deleted);
		$this->assertSame(['a', 'b'], $deleted);
		$this->assertSame([], $repository->list('model', 'uploads', 'alice'));
	}

	public function testCleanupDoesNotDeleteFilesBelongingToAnotherOwner(): void {
		$records = [];
		$repository = $this->repository($records);
		$repository->set('model', 'uploads', 'alice', 'a.pdf', $this->reference('openai', 'a'));
		$repository->set('model', 'uploads', 'bob', 'b.pdf', $this->reference('openai', 'b'));
		$deleted = [];
		$this->service($repository, $this->fileModel($deleted))->cleanup('uploads', 'alice');
		$this->assertSame(['a'], $deleted);
		$this->assertCount(1, $repository->list('model', 'uploads', 'bob'));
		$this->assertSame(['model'], $repository->listRegisteredServiceIds('uploads', 'bob'));
	}

	public function testProviderMismatchPreventsDeletionAndPreservesOwnerIndex(): void {
		$records = [];
		$repository = $this->repository($records);
		$repository->set('model', 'uploads', 'alice', 'a.pdf', $this->reference('other-provider', 'a'));
		$deleted = [];
		try {
			$this->service($repository, $this->fileModel($deleted))->cleanup('uploads', 'alice');
			$this->fail('Provider mismatch must block file deletion.');
		}
		catch(\RuntimeException $exception) {
			$this->assertStringContainsString('other-provider', $exception->getMessage());
			$this->assertStringContainsString('openai', $exception->getMessage());
		}
		$this->assertSame([], $deleted);
		$this->assertCount(1, $repository->list('model', 'uploads', 'alice'));
		$this->assertSame(['model'], $repository->listRegisteredServiceIds('uploads', 'alice'));
	}

	public function testRemoteDeletionFailureRetainsReferenceAndIndexForRetry(): void {
		$records = [];
		$repository = $this->repository($records);
		$repository->set('model', 'uploads', 'alice', 'a.pdf', $this->reference('openai', 'a'));
		$deleted = [];
		$model = $this->fileModel($deleted);
		$model->method('deleteFile')->willThrowException(new \RuntimeException('Remote deletion failed'));
		try {
			$this->service($repository, $model)->cleanup('uploads', 'alice');
			$this->fail('Failed remote cleanup must not discard the reference.');
		}
		catch(\RuntimeException $exception) {
			$this->assertSame('Remote deletion failed', $exception->getMessage());
		}
		$this->assertCount(1, $repository->list('model', 'uploads', 'alice'));
		$this->assertSame(['model'], $repository->listRegisteredServiceIds('uploads', 'alice'));
	}

	public function testRepeatedCleanupDoesNotRepeatRemoteDeletion(): void {
		$records = [];
		$repository = $this->repository($records);
		$repository->set('model', 'uploads', 'alice', 'file.pdf', $this->reference('openai', 'a'));
		$deleted = [];
		$service = $this->service($repository, $this->fileModel($deleted));
		$service->cleanup('uploads', 'alice');
		$service->cleanup('uploads', 'alice');
		$this->assertSame(['a'], $deleted);
	}

	public function testUnrelatedFileDeletionEventNeverCallsProvider(): void {
		$records = [];
		$repository = $this->repository($records);
		$repository->set('model', 'uploads', 'alice', 'file.pdf', $this->reference('openai', 'a'));
		$deleted = [];
		$this->service($repository, $this->fileModel($deleted))->handle(new ManagedFileStorageDeletingEvent('uploads', 'alice', 'single-file'));
		$this->assertSame([], $deleted);
		$this->assertCount(1, $repository->list('model', 'uploads', 'alice'));
	}

	private function reference(string $provider, string $id): AiProviderFileReference {
		return new AiProviderFileReference($provider, $id, 'hash-' . $id, 'document.pdf', 'application/pdf', 40);
	}

	/** @param array<int,string> $deleted */
	private function fileModel(array &$deleted): IAiFileCapableChatModel {
		$model = $this->createStub(IAiFileCapableChatModel::class);
		$model->method('getFileProviderName')->willReturn('openai');
		$model->method('deleteFile')->willReturnCallback(static function(AiProviderFileReference $reference) use (&$deleted): void {
			$deleted[] = $reference->getOpaqueId();
		});
		return $model;
	}

	private function service(AiProviderFileReferenceStateRepository $repository, IAiFileCapableChatModel $model): AiProviderFileOwnerCleanupService {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('getGroup')->willReturn(['model' => $this->serviceSettings()]);
		$settings->method('get')->willReturnCallback(function(string $group, string $id, array $default = []): array {
			return match($group) {
				'service-llm' => $this->serviceSettings(),
				'connection' => ['baseUrl' => 'https://example.invalid', 'type' => 'http', 'auth' => ['type' => 'none'], 'enabled' => true],
				default => $default
			};
		});
		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstancesByInterface')->willReturn([new OpenAiChatServiceDriverDefinition()]);
		$classMap->method('getClassByInterfaceName')->willReturn('test-model');
		$classMap->method('instantiate')->willReturn($model);
		return new AiProviderFileOwnerCleanupService(
			$repository,
			new ConfiguredServiceRuntimeResolver($settings, $classMap, $this->createStub(IAgentConfigValueResolver::class)),
			$settings
		);
	}

	/** @return array<string,mixed> */
	private function serviceSettings(): array {
		return ['serviceType' => 'llm', 'connection' => 'provider', 'driver' => 'openai-chat', 'model' => 'gpt-test', 'enabled' => true];
	}

	/** @param array<string,mixed> $records */
	private function repository(array &$records): AiProviderFileReferenceStateRepository {
		$state = $this->createStub(IStateStore::class);
		$state->method('get')->willReturnCallback(static function(string $key, mixed $default = null) use (&$records): mixed {
			return $records[$key] ?? $default;
		});
		$state->method('set')->willReturnCallback(static function(string $key, mixed $value, ?int $ttlSeconds = null) use (&$records): void {
			$records[$key] = $value;
		});
		$state->method('listKeys')->willReturnCallback(static function(string $prefix) use (&$records): array {
			return array_values(array_filter(array_keys($records), static fn(string $key): bool => str_starts_with($key, $prefix)));
		});
		$state->method('delete')->willReturnCallback(static function(string $key) use (&$records): bool {
			$exists = array_key_exists($key, $records);
			unset($records[$key]);
			return $exists;
		});
		return new AiProviderFileReferenceStateRepository($state);
	}
}
