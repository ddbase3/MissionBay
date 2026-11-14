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

use Base3\AssistantFoundation\Api\IAiFileCapableChatModel;
use Base3\AssistantFoundation\Dto\AiFileResource;
use Base3\AssistantFoundation\Dto\AiProviderFileMetadata;
use Base3\AssistantFoundation\Dto\AiProviderFileReference;
use Base3\CoreFoundation\State\Api\IStateStore;
use Base3\MissionBay\Service\AiProviderFileReferenceResolver;
use Base3\MissionBay\Service\AiProviderFileReferenceStateRepository;
use PHPUnit\Framework\TestCase;
use Base3\ResourceFoundation\Api\IFileStorage;
use Base3\ResourceFoundation\Api\IManagedFileStorageService;

final class AiProviderFileReferenceResolverTest extends TestCase {

	public function testRejectsBlankServiceAndOwnerIdentifiers(): void {
		$values = [];
		$deleted = [];
		$resolver = $this->resolver([], $values, $deleted);
		$model = $this->model();
		foreach ([[' ', 'group', 'owner'], ['llm', ' ', 'owner'], ['llm', 'group', ' ']] as $ids) {
			try {
				$resolver->resolve($model, ...$ids);
				$this->fail('Blank owner or service identifier was accepted.');
			} catch (\InvalidArgumentException $exception) {
				$this->assertStringContainsString('required', $exception->getMessage());
			}
		}
		$this->assertSame([], $values);
	}

	public function testMissingStorageReturnsNoReferencesButRegistersService(): void {
		$values = [];
		$deleted = [];
		$resolver = $this->resolver(null, $values, $deleted);
		$this->assertSame([], $resolver->resolve($this->model(), 'llm', 'group', 'alice'));
		$repository = new AiProviderFileReferenceStateRepository($this->stateStore($values));
		$this->assertSame(['llm'], $repository->listRegisteredServiceIds('group', 'alice'));
		$this->assertSame([], $deleted);
	}

	public function testPdfIsUploadedWithContentHashAndLockIsReleased(): void {
		$values = [];
		$deleted = [];
		$uploaded = [];
		$model = $this->model();
		$model->method('uploadFile')->willReturnCallback(static function(AiFileResource $file) use (&$uploaded): AiProviderFileReference {
			$uploaded[] = $file;
			return new AiProviderFileReference('vendor', 'remote-1', $file->getContentHash(), $file->getName(), 'application/pdf', strlen($file->getContent()));
		});
		$resolver = $this->resolver(['document.pdf' => '%PDF-content'], $values, $deleted);
		$result = $resolver->resolve($model, 'llm', 'group', 'alice');
		$this->assertCount(1, $result);
		$this->assertSame('remote-1', $result[0]->getOpaqueId());
		$this->assertSame(hash('sha256', '%PDF-content'), $result[0]->getContentHash());
		$this->assertCount(1, $uploaded);
		$this->assertSame('document.pdf', $uploaded[0]->getPath());
		$this->assertSame('%PDF-content', $uploaded[0]->getContent());
		$repository = new AiProviderFileReferenceStateRepository($this->stateStore($values));
		$this->assertSame('remote-1', $repository->get('llm', 'group', 'alice', 'document.pdf')->getOpaqueId());
		$this->assertSame([], array_filter(array_keys($values), static fn(string $key): bool => str_starts_with($key, 'locks.ai.provider-files.')));
	}

	public function testIdenticalValidRemoteReferenceIsReusedWithoutUploading(): void {
		$values = [];
		$deleted = [];
		$repository = new AiProviderFileReferenceStateRepository($this->stateStore($values));
		$repository->set('llm', 'group', 'alice', 'report.pdf', $this->reference('old', 'report.pdf', 'unchanged'));
		$model = $this->model();
		$model->method('getFileMetadata')->willReturn(new AiProviderFileMetadata('old', 'report.pdf', 9));
		$result = $this->resolver(['report.pdf' => 'unchanged'], $values, $deleted)->resolve($model, 'llm', 'group', 'alice');
		$this->assertCount(1, $result);
		$this->assertSame('old', $result[0]->getOpaqueId());
		$this->assertSame([], $deleted);
	}

	public function testChangedPdfUploadsReplacementAndDeletesPreviousReference(): void {
		$values = [];
		$deleted = [];
		$repository = new AiProviderFileReferenceStateRepository($this->stateStore($values));
		$repository->set('llm', 'group', 'alice', 'report.pdf', $this->reference('old', 'report.pdf', 'previous'));
		$model = $this->model($deleted);
		$model->method('uploadFile')->willReturnCallback(static fn(AiFileResource $file): AiProviderFileReference => new AiProviderFileReference('vendor', 'new', $file->getContentHash(), 'report.pdf', 'application/pdf', 7));
		$result = $this->resolver(['report.pdf' => 'changed'], $values, $deleted)->resolve($model, 'llm', 'group', 'alice');
		$this->assertSame('new', $result[0]->getOpaqueId());
		$this->assertSame(['old'], $deleted);
		$this->assertSame('new', $repository->get('llm', 'group', 'alice', 'report.pdf')->getOpaqueId());
	}

	public function testUnsupportedFilesAreRejectedBeforeUpload(): void {
		$values = [];
		$deleted = [];
		$resolver = $this->resolver(['report.txt' => 'plain'], $values, $deleted);
		$this->expectException(\RuntimeException::class);
		$resolver->resolve($this->model(), 'llm', 'group', 'alice');
	}

	public function testRemovedFilesAreDeletedFromProviderAndState(): void {
		$values = [];
		$deleted = [];
		$repository = new AiProviderFileReferenceStateRepository($this->stateStore($values));
		$repository->set('llm', 'group', 'alice', 'old.pdf', $this->reference('old', 'old.pdf', 'obsolete'));
		$result = $this->resolver([], $values, $deleted)->resolve($this->model($deleted), 'llm', 'group', 'alice');
		$this->assertSame([], $result);
		$this->assertSame(['old'], $deleted);
		$this->assertSame([], $repository->list('llm', 'group', 'alice'));
	}

	public function testProviderMismatchPreventsDeletingForeignProviderFile(): void {
		$values = [];
		$deleted = [];
		$repository = new AiProviderFileReferenceStateRepository($this->stateStore($values));
		$reference = new AiProviderFileReference('other-provider', 'foreign', hash('sha256', 'data'), 'old.pdf', 'application/pdf', 4);
		$repository->set('llm', 'group', 'alice', 'old.pdf', $reference);
		try {
			$this->resolver(null, $values, $deleted)->resolve($this->model($deleted), 'llm', 'group', 'alice');
			$this->fail('Cross-provider cleanup was incorrectly permitted.');
		} catch (\RuntimeException $exception) {
			$this->assertStringContainsString('other-provider', $exception->getMessage());
		}
		$this->assertSame([], $deleted);
		$this->assertSame('foreign', $repository->get('llm', 'group', 'alice', 'old.pdf')->getOpaqueId());
	}

	private function reference(string $id, string $path, string $content): AiProviderFileReference {
		return new AiProviderFileReference('vendor', $id, hash('sha256', $content), $path, 'application/pdf', strlen($content));
	}

	/** @param array<int,string> $deleted */
	private function model(array &$deleted = []): IAiFileCapableChatModel {
		$model = $this->createStub(IAiFileCapableChatModel::class);
		$model->method('getFileProviderName')->willReturn('vendor');
		$model->method('deleteFile')->willReturnCallback(static function(AiProviderFileReference $reference) use (&$deleted): void {
			$deleted[] = $reference->getOpaqueId();
		});
		return $model;
	}

	/** @param array<string,string>|null $files Null means no owned collection. @param array<string,mixed> $values @param array<int,string> $deleted */
	private function resolver(?array $files, array &$values, array &$deleted): AiProviderFileReferenceResolver {
		$managed = $this->createStub(IManagedFileStorageService::class);
		$managed->method('has')->willReturn($files !== null);
		$storage = $this->createStub(IFileStorage::class);
		$storage->method('list')->willReturn(array_merge([
			['type' => 'directory', 'name' => 'ignored'],
			['type' => 'file', 'name' => '  '],
			'not-an-entry'
		], array_map(static fn(string $path): array => ['type' => 'file', 'name' => $path], array_keys($files ?? []))));
		$storage->method('read')->willReturnCallback(static fn(string $path): string => $files[$path]);
		$managed->method('openOrCreate')->willReturn($storage);
		$state = $this->stateStore($values);
		return new AiProviderFileReferenceResolver($managed, $state, new AiProviderFileReferenceStateRepository($state));
	}

	/** @param array<string,mixed> $values */
	private function stateStore(array &$values): IStateStore {
		$state = $this->createStub(IStateStore::class);
		$state->method('get')->willReturnCallback(static function(string $key, mixed $default = null) use (&$values): mixed { return $values[$key] ?? $default; });
		$state->method('set')->willReturnCallback(static function(string $key, mixed $value, ?int $ttlSeconds = null) use (&$values): void { $values[$key] = $value; });
		$state->method('setIfNotExists')->willReturnCallback(static function(string $key, mixed $value, ?int $ttlSeconds = null) use (&$values): bool {
			if (array_key_exists($key, $values)) {
				return false;
			}
			$values[$key] = $value;
			return true;
		});
		$state->method('delete')->willReturnCallback(static function(string $key) use (&$values): bool {
			$exists = array_key_exists($key, $values);
			unset($values[$key]);
			return $exists;
		});
		$state->method('listKeys')->willReturnCallback(static function(string $prefix) use (&$values): array {
			return array_values(array_filter(array_keys($values), static fn(string $key): bool => str_starts_with($key, $prefix)));
		});
		return $state;
	}
}
