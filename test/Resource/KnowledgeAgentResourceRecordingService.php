<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use MissionBay\Api\IAgentKnowledgeService;

final class KnowledgeAgentResourceRecordingService implements IAgentKnowledgeService {

	/** @var array<int,array<string,mixed>> */
	public array $entries = [];
	/** @var array<int,array<int,array<string,mixed>>> */
	public array $findResponses = [];
	/** @var array<int,array<string,mixed>> */
	public array $searchResults = [];
	/** @var array<int,array<string,mixed>> */
	public array $created = [];
	/** @var array<int,array<string,mixed>> */
	public array $updated = [];
	/** @var array<int,int> */
	public array $touched = [];
	/** @var array<int,array{int,?string}> */
	public array $deleted = [];
	/** @var array<int,array{array,int,int}> */
	public array $findCalls = [];
	/** @var array<int,array{string,array,int,int}> */
	public array $searchCalls = [];
	public bool $throwOnCreate = false;
	public bool $updateSuccess = true;
	public bool $deleteSuccess = true;
	public int $nextId = 42;

	public function getMemoryTypes(): array {
		return ['task', 'episodic', 'semantic', 'procedural'];
	}

	public function getAllowedStatuses(string $memoryType): array {
		return match ($memoryType) {
			'task' => ['open', 'closed'],
			'episodic' => ['open', 'resolved'],
			'semantic' => ['valid'],
			'procedural' => ['active'],
			default => []
		};
	}

	public function createEntry(array $data): int {
		if ($this->throwOnCreate) {
			throw new \RuntimeException('Write failed');
		}
		$this->created[] = $data;
		$id = $this->nextId++;
		$this->entries[$id] = ['id' => $id] + $data;
		return $id;
	}

	public function updateEntry(int $id, array $data): bool {
		$this->updated[$id] = $data;
		if ($this->updateSuccess && isset($this->entries[$id])) {
			$this->entries[$id] = array_replace($this->entries[$id], $data);
		}
		return $this->updateSuccess;
	}

	public function deleteEntry(int $id, ?string $deletedBy = null): bool {
		$this->deleted[] = [$id, $deletedBy];
		return $this->deleteSuccess;
	}

	public function getEntryById(int $id, bool $includeDeleted = false): ?array {
		return $this->entries[$id] ?? null;
	}

	public function findEntries(array $filters = [], int $limit = 50, int $offset = 0): array {
		$this->findCalls[] = [$filters, $limit, $offset];
		return array_shift($this->findResponses) ?? [];
	}

	public function searchEntries(string $query, array $options = [], int $limit = 20, int $offset = 0): array {
		$this->searchCalls[] = [$query, $options, $limit, $offset];
		return $this->searchResults;
	}

	public function buildPromptExtract(string $query, array $options = [], int $limit = 10): string {
		return '';
	}

	public function touchEntry(int $id): bool {
		$this->touched[] = $id;
		return true;
	}

	public function isValidStatusForType(string $memoryType, ?string $status): bool {
		return in_array($status, $this->getAllowedStatuses($memoryType), true);
	}

	public function isMutableByLlm(array $entry): bool {
		return true;
	}

	public function isDeletableByLlm(array $entry): bool {
		return true;
	}

	public function isEntryValidAt(array $entry, ?string $at = null): bool {
		return true;
	}

	public function isEntryExpired(array $entry, ?string $at = null): bool {
		return false;
	}
}
