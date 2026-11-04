<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use Base3\Settings\Api\ISettingsStore;
use MissionBay\Service\RetrievalCollectionConfigRepository;
use PHPUnit\Framework\TestCase;

final class RetrievalCollectionConfigRepositoryTest extends TestCase {

	public function testCollectionsAreNormalizedSortedAndInvalidRecordsAreOmitted(): void {
		$records = [
			'Z-2' => ['backend_collection' => ' zeta '],
			'a_10' => ['backend_collection' => 'alpha'],
			'a_2' => ['backend_collection' => 'beta'],
			' invalid ! ' => ['backend_collection' => '  '],
			'bad' => 'not-an-array',
			9 => ['backend_collection' => 'numeric'],
			' ONLY ! ' => ['backend_collection' => 'kept']
		];
		$stored = $records;
		$repository = $this->repository($stored);
		$this->assertSame([
			'a_2' => ['backend_collection' => 'beta'],
			'a_10' => ['backend_collection' => 'alpha'],
			'only' => ['backend_collection' => 'kept'],
			'z-2' => ['backend_collection' => 'zeta']
		], $repository->getCollections());
	}

	public function testNamesCanBeResolvedFromSanitizedKeys(): void {
		$stored = ['foobar' => ['backend_collection' => 'physical']];
		$repository = $this->repository($stored);
		$this->assertTrue($repository->hasCollection(' Foo Bar! '));
		$this->assertSame('physical', $repository->getBackendCollectionName('Foo Bar!'));
		$this->assertFalse($repository->hasCollection('??'));
	}

	public function testUnknownOrEmptyKeysAreRejectedOnLookup(): void {
		$stored = [];
		$repository = $this->repository($stored);
		foreach (['missing', '  !! '] as $key) {
			try {
				$repository->getBackendCollectionName($key);
				$this->fail('Unknown collection unexpectedly resolved.');
			} catch (\InvalidArgumentException $exception) {
				$this->assertStringContainsString('Unknown retrieval collection', $exception->getMessage());
			}
		}
	}

	public function testSaveNormalizesAndPersistsCollectionName(): void {
		$stored = [];
		$calls = [];
		$repository = $this->repository($stored, $calls);
		$repository->saveCollection(' Read Only! ', ' vector_one ');
		$this->assertSame(['backend_collection' => 'vector_one'], $stored['readonly']);
		$this->assertSame([['set', 'retrieval-collection', 'readonly'], ['save']], $calls);
		$this->assertSame('vector_one', $repository->getBackendCollectionName('READ ONLY'));
	}

	public function testSaveRejectsBlankKeyAndBlankBackend(): void {
		$stored = [];
		$calls = [];
		$repository = $this->repository($stored, $calls);
		foreach ([[' ! ', 'backend'], ['key', '  ']] as $pair) {
			try {
				$repository->saveCollection(...$pair);
				$this->fail('Invalid collection was saved.');
			} catch (\InvalidArgumentException $exception) {
				$this->assertNotEmpty($exception->getMessage());
			}
		}
		$this->assertSame([], $calls);
	}

	public function testDuplicateBackendForDifferentKeyIsRejectedButUpdatingSameKeyIsAllowed(): void {
		$stored = ['first' => ['backend_collection' => 'shared']];
		$calls = [];
		$repository = $this->repository($stored, $calls);
		try {
			$repository->saveCollection('second', 'shared');
			$this->fail('Duplicate backend was saved.');
		} catch (\InvalidArgumentException $exception) {
			$this->assertStringContainsString('first', $exception->getMessage());
		}
		$this->assertSame([], $calls);
		$repository->saveCollection('FIRST', ' shared ');
		$this->assertSame(['backend_collection' => 'shared'], $stored['first']);
		$this->assertCount(2, $calls);
	}

	public function testRemoveIgnoresInvalidKeysAndDeletesValidEntries(): void {
		$stored = ['foo' => ['backend_collection' => 'physical']];
		$calls = [];
		$repository = $this->repository($stored, $calls);
		$repository->removeCollection(' !!! ');
		$this->assertSame([], $calls);
		$repository->removeCollection(' FOO! ');
		$this->assertSame([], $stored);
		$this->assertSame([['remove', 'retrieval-collection', 'foo'], ['save']], $calls);
	}

	/** @param array<string,mixed> $stored @param array<int,array<int,string>> $calls */
	private function repository(array &$stored, array &$calls = []): RetrievalCollectionConfigRepository {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('getGroup')->willReturnCallback(static function(string $group) use (&$stored): array {
			return $group === 'retrieval-collection' ? $stored : [];
		});
		$settings->method('set')->willReturnCallback(static function(string $group, string $name, array $data) use (&$stored, &$calls): void {
			$calls[] = ['set', $group, $name];
			$stored[$name] = $data;
		});
		$settings->method('remove')->willReturnCallback(static function(string $group, string $name) use (&$stored, &$calls): void {
			$calls[] = ['remove', $group, $name];
			unset($stored[$name]);
		});
		$settings->method('save')->willReturnCallback(static function() use (&$calls): void {
			$calls[] = ['save'];
		});
		return new RetrievalCollectionConfigRepository($settings);
	}
}
