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
use Base3\CoreFoundation\State\Api\IStateStore;
use Base3\MissionBay\Service\AiProviderFileReferenceStateRepository;
use PHPUnit\Framework\TestCase;

final class AiProviderFileReferenceStateRepositoryTest extends TestCase {

	public function testRoundTripAndOwnerIndexPreserveServiceAndPathIsolation(): void {
		$values = [];
		$repository = new AiProviderFileReferenceStateRepository($this->stateStore($values));
		$a = $this->reference('file-a');
		$b = $this->reference('file-b');
		$repository->set('llm-b', 'uploads', 'alice', 'a.txt', $a);
		$repository->set('llm-a', 'uploads', 'alice', 'a.txt', $b);
		$repository->set('llm-b', 'uploads', 'bob', 'a.txt', $b);
		$repository->set('llm-b', 'uploads', 'alice', 'b.txt', $b);
		$this->assertSame('file-a', $repository->get('llm-b', 'uploads', 'alice', 'a.txt')->getOpaqueId());
		$this->assertSame('file-b', $repository->get('llm-a', 'uploads', 'alice', 'a.txt')->getOpaqueId());
		$this->assertNull($repository->get('llm-b', 'uploads', 'alice', 'missing.txt'));
		$this->assertSame(['llm-a', 'llm-b'], $repository->listRegisteredServiceIds('uploads', 'alice'));
		$this->assertCount(2, $repository->list('llm-b', 'uploads', 'alice'));
		$this->assertCount(1, $repository->list('llm-b', 'uploads', 'bob'));
	}

	public function testMalformedRecordsAreIgnoredOrRemovedWithoutLeakingOtherEntries(): void {
		$values = [];
		$repository = new AiProviderFileReferenceStateRepository($this->stateStore($values));
		$repository->set('llm', 'uploads', 'alice', 'x.txt', $this->reference('x'));
		$records = $repository->list('llm', 'uploads', 'alice');
		$this->assertCount(1, $records);
		$key = $records[0]['key'];
		$values[$key]['service_id'] = 'different';
		$this->assertNull($repository->get('llm', 'uploads', 'alice', 'x.txt'));
		$this->assertSame([], $repository->list('llm', 'uploads', 'alice'));
		$this->assertArrayNotHasKey($key, $values);
	}

	public function testDeletionAndIndexCleanupLeaveOtherServiceReferencesIntact(): void {
		$values = [];
		$repository = new AiProviderFileReferenceStateRepository($this->stateStore($values));
		$repository->set('llm', 'g', 'user', 'x', $this->reference('x'));
		$repository->set('second', 'g', 'user', 'y', $this->reference('y'));
		$repository->deleteKey($repository->list('llm', 'g', 'user')[0]['key']);
		$repository->clearOwnerIndex('g', 'user');
		$repository->flush();
		$this->assertSame([], $repository->listRegisteredServiceIds('g', 'user'));
		$this->assertSame([], $repository->list('llm', 'g', 'user'));
		$this->assertSame('y', $repository->get('second', 'g', 'user', 'y')->getOpaqueId());
	}

	public function testBlankIdentifiersAreRejected(): void {
		$values = [];
		$repository = new AiProviderFileReferenceStateRepository($this->stateStore($values));
		foreach ([['', 'g', 'n', 'p'], ['s', '', 'n', 'p'], ['s', 'g', ' ', 'p'], ['s', 'g', 'n', '']] as $args) {
			try {
				$repository->get(...$args);
				$this->fail('Blank reference key accepted.');
			} catch (\InvalidArgumentException $exception) {
				$this->assertNotEmpty($exception->getMessage());
			}
		}
	}

	private function reference(string $id): AiProviderFileReference {
		return new AiProviderFileReference('provider', $id, 'hash', 'document.txt', 'text/plain', 10);
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
