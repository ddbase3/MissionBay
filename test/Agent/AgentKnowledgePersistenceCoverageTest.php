<?php declare(strict_types=1);

namespace MissionBay\Test\Agent;

use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Database\Api\IDatabase;
use Base3\Session\Api\ISession;
use MissionBay\Agent\AgentKnowledgeService;
use PHPUnit\Framework\TestCase;

final class AgentKnowledgePersistenceCoverageTest extends TestCase {

	public function testCreateEntryPersistsNormalizedContentUnderTrustedUserIdentity(): void {
		$queries = [];
		$service = $this->service($queries);
		$id = $service->createEntry([
			'memory_type' => 'semantic', 'memory_key' => ' topic ', 'title' => ' Title ',
			'content' => ' Content ', 'status' => 'valid', 'tags_json' => ['alpha'],
			'is_mutable_by_llm' => false
		]);
		$this->assertSame(23, $id);
		$this->assertCount(2, $queries);
		$this->assertStringContainsString('CREATE TABLE IF NOT EXISTS', $queries[0]);
		$this->assertStringContainsString("`title` = 'Title'", $queries[1]);
		$this->assertStringContainsString("`content` = 'Content'", $queries[1]);
		$this->assertStringContainsString("`ident` = 'u:42'", $queries[1]);
		$this->assertStringContainsString('`is_mutable_by_llm` = 0', $queries[1]);
	}

	public function testCreateEntryRejectsUnsupportedKnowledgeType(): void {
		$queries = [];
		$this->expectException(\InvalidArgumentException::class);
		$this->service($queries)->createEntry(['memory_type' => 'invalid', 'title' => 'A', 'content' => 'B']);
	}

	public function testCreateEntryRejectsEmptyTitle(): void {
		$queries = [];
		$this->expectException(\InvalidArgumentException::class);
		$this->service($queries)->createEntry(['memory_type' => 'task', 'title' => ' ', 'content' => 'B']);
	}

	public function testCreateEntryRejectsEmptyContent(): void {
		$queries = [];
		$this->expectException(\InvalidArgumentException::class);
		$this->service($queries)->createEntry(['memory_type' => 'task', 'title' => 'A', 'content' => ' ']);
	}

	public function testCreateEntryRejectsStatusFromDifferentKnowledgeType(): void {
		$queries = [];
		$this->expectException(\InvalidArgumentException::class);
		$this->service($queries)->createEntry(['memory_type' => 'semantic', 'title' => 'A', 'content' => 'B', 'status' => 'blocked']);
	}

	public function testSchemaInitializationErrorsAreNotSwallowed(): void {
		$queries = [];
		$this->expectException(\RuntimeException::class);
		$this->service($queries, null, [], true)->createEntry(['memory_type' => 'task', 'title' => 'A', 'content' => 'B']);
	}

	public function testGetEntryByIdHydratesOnlyOwnedRow(): void {
		$queries = [];
		$row = $this->row(['id' => '7', 'tags_json' => '["red"]']);
		$service = $this->service($queries, $row);
		$result = $service->getEntryById(7);
		$this->assertSame(7, $result['id']);
		$this->assertSame(['red'], $result['tags_json']);
		$this->assertStringContainsString('`is_deleted` = 0', $queries[1]);
	}

	public function testGetEntryByIdCanExplicitlyIncludeDeletedRows(): void {
		$queries = [];
		$service = $this->service($queries, $this->row(['is_deleted' => 1]));
		$this->assertNotNull($service->getEntryById(7, true));
		$this->assertStringNotContainsString('`is_deleted` = 0', $queries[1]);
	}

	public function testGetEntryByIdRejectsForeignIdentity(): void {
		$queries = [];
		$service = $this->service($queries, $this->row(['ident' => 'u:999']));
		$this->assertNull($service->getEntryById(7));
	}

	public function testGetEntryByIdHandlesMissingRow(): void {
		$queries = [];
		$this->assertNull($this->service($queries)->getEntryById(7));
	}

	public function testUpdateEntryDoesNotWriteWhenMissing(): void {
		$queries = [];
		$this->assertFalse($this->service($queries)->updateEntry(7, ['title' => 'Changed']));
		$this->assertCount(2, $queries);
	}

	public function testUpdateEntryRejectsLockedKnowledge(): void {
		$queries = [];
		$service = $this->service($queries, $this->row(['is_locked' => 1]));
		$this->assertFalse($service->updateEntry(7, ['content' => 'new']));
		$this->assertCount(2, $queries);
	}

	public function testUpdateEntryRejectsLlmModificationWhenDisabled(): void {
		$queries = [];
		$service = $this->service($queries, $this->row(['is_mutable_by_llm' => 0]));
		$this->assertFalse($service->updateEntry(7, ['updated_by' => 'llm', 'content' => 'new']));
		$this->assertCount(2, $queries);
	}

	public function testUpdateEntryRejectsInvalidStatusChange(): void {
		$queries = [];
		$service = $this->service($queries, $this->row());
		$this->expectException(\InvalidArgumentException::class);
		$service->updateEntry(7, ['status' => 'blocked']);
	}

	public function testUpdateEntryRejectsBlankReplacementTitle(): void {
		$queries = [];
		$service = $this->service($queries, $this->row());
		$this->expectException(\InvalidArgumentException::class);
		$service->updateEntry(7, ['title' => '  ']);
	}

	public function testUpdateEntryRejectsBlankReplacementContent(): void {
		$queries = [];
		$service = $this->service($queries, $this->row());
		$this->expectException(\InvalidArgumentException::class);
		$service->updateEntry(7, ['content' => '  ']);
	}

	public function testEmptyUpdateDoesNotWriteAnotherQuery(): void {
		$queries = [];
		$service = $this->service($queries, $this->row());
		$this->assertTrue($service->updateEntry(7, []));
		$this->assertCount(2, $queries);
	}

	public function testUpdateEntryWritesRequestedFieldsWithoutOverwritingUnchangedFields(): void {
		$queries = [];
		$service = $this->service($queries, $this->row());
		$this->assertTrue($service->updateEntry(7, [
			'content' => ' updated ', 'priority' => 9, 'tags_json' => ['beta'],
			'updated_by' => 'editor', 'is_mutable_by_llm' => false
		]));
		$this->assertCount(3, $queries);
		$this->assertStringContainsString("`content` = 'updated'", $queries[2]);
		$this->assertStringContainsString('`priority` = 9', $queries[2]);
		$this->assertStringContainsString('`is_mutable_by_llm` = 0', $queries[2]);
		$this->assertStringContainsString('WHERE `id` = 7', $queries[2]);
	}

	public function testDeleteEntryRejectsLockedAndForeignEntries(): void {
		$lockedQueries = [];
		$this->assertFalse($this->service($lockedQueries, $this->row(['is_locked' => 1]))->deleteEntry(7));
		$foreignQueries = [];
		$this->assertFalse($this->service($foreignQueries, $this->row(['ident' => 'u:99']))->deleteEntry(7));
	}

	public function testLlmCannotDeleteEntryWhenDeletionFlagIsDisabled(): void {
		$queries = [];
		$service = $this->service($queries, $this->row(['is_deletable_by_llm' => 0]));
		$this->assertFalse($service->deleteEntry(7, 'llm'));
		$this->assertCount(2, $queries);
	}

	public function testAuthorizedManualDeleteMarksEntryDeleted(): void {
		$queries = [];
		$service = $this->service($queries, $this->row(['is_deletable_by_llm' => 0]));
		$this->assertTrue($service->deleteEntry(7, 'editor'));
		$this->assertStringContainsString('`is_deleted` = 1', $queries[2]);
		$this->assertStringContainsString("`updated_by` = 'editor'", $queries[2]);
	}

	public function testTouchEntryRecordsAccessWhenAuthorized(): void {
		$queries = [];
		$service = $this->service($queries, $this->row());
		$this->assertTrue($service->touchEntry(7));
		$this->assertStringContainsString('`last_accessed_at` = NOW()', $queries[2]);
	}

	public function testTouchEntryReturnsFalseWhenNoRowIsAffected(): void {
		$queries = [];
		$service = $this->service($queries, $this->row(), [], false, 0);
		$this->assertFalse($service->touchEntry(7));
	}

	public function testTouchEntryRejectsForeignIdentity(): void {
		$queries = [];
		$service = $this->service($queries, $this->row(['ident' => 'u:other']));
		$this->assertFalse($service->touchEntry(7));
	}

	public function testFindEntriesMergesSessionAndUserRowsBeforeLimit(): void {
		$queries = [];
		$rows = [
			$this->row(['id' => 1, 'scope' => 'session', 'ident' => 's:session-abc', 'memory_key' => 'same']),
			$this->row(['id' => 2, 'scope' => 'user', 'memory_key' => 'same']),
			$this->row(['id' => 3, 'scope' => 'user', 'memory_key' => 'different'])
		];
		$service = $this->service($queries, null, $rows);
		$this->assertSame([2], array_column($service->findEntries([], 1), 'id'));
		$this->assertStringContainsString("`ident` IN ('s:session-abc','u:42')", $queries[1]);
	}

	public function testSearchEntriesReturnsEmptyWhenNoCandidatesExist(): void {
		$queries = [];
		$service = $this->service($queries);
		$this->assertSame([], $service->searchEntries('anything', ['memory_type' => 'semantic']));
	}

	public function testSearchEntriesRanksBroadCandidatesAndRespectsLimit(): void {
		$queries = [];
		$rows = [
			$this->row(['id' => 1, 'memory_key' => 'unrelated-key', 'title' => 'unrelated', 'priority' => 9]),
			$this->row(['id' => 2, 'memory_key' => 'alpha-key', 'title' => 'alpha result', 'priority' => 1])
		];
		$service = $this->service($queries, null, $rows);
		$this->assertSame([2], array_column($service->searchEntries('alpha', [], 1), 'id'));
	}

	public function testBuildPromptExtractUsesSummaryAndTypeLabel(): void {
		$queries = [];
		$rows = [$this->row(['title' => 'Rule', 'summary' => 'Use approved process'])];
		$service = $this->service($queries, null, $rows);
		$this->assertSame('[SEMANTIC] Rule: Use approved process', $service->buildPromptExtract('', [], 5));
	}

	public function testBuildPromptExtractUsesContentWhenSummaryIsMissing(): void {
		$queries = [];
		$rows = [$this->row(['title' => 'Rule', 'summary' => ' ', 'content' => 'Original text'])];
		$service = $this->service($queries, null, $rows);
		$this->assertSame('[SEMANTIC] Rule: Original text', $service->buildPromptExtract('', [], 5));
	}

	public function testBuildPromptExtractReturnsEmptyStringWithoutKnowledge(): void {
		$queries = [];
		$this->assertSame('', $this->service($queries)->buildPromptExtract('no matches'));
	}

	public function testCuratedEntriesUseInjectionOrderAndNeverReturnMoreThanRequested(): void {
		$queries = [];
		$rows = [
			$this->row(['id' => 1, 'meta_json' => '{"inject_order":20}']),
			$this->row(['id' => 2, 'memory_key' => 'other', 'meta_json' => '{"inject_order":10}'])
		];
		$service = $this->service($queries, null, $rows);
		$this->assertSame([2], array_column($service->loadCuratedEntries([], 1), 'id'));
		$this->assertStringContainsString('ORDER BY', $queries[1]);
	}

	private function row(array $changes = []): array {
		return array_replace([
			'id' => 7, 'ident' => 'u:42', 'memory_type' => 'semantic',
			'memory_key' => 'entry', 'status' => 'valid', 'title' => 'Existing',
			'content' => 'Existing content', 'summary' => 'Summary', 'scope' => 'user',
			'priority' => 0, 'is_locked' => 0, 'is_mutable_by_llm' => 1,
			'is_deletable_by_llm' => 1, 'is_deleted' => 0, 'confidence' => null,
			'tags_json' => null, 'entity_refs_json' => null, 'meta_json' => null
		], $changes);
	}

	private function service(array &$queries, ?array $row = null, array $rows = [], bool $error = false, int $affectedRows = 1): AgentKnowledgeService {
		$db = $this->createStub(IDatabase::class);
		$db->method('escape')->willReturnCallback(static fn(string $value): string => addslashes($value));
		$db->method('nonQuery')->willReturnCallback(static function(string $query) use (&$queries): void {
			$queries[] = $query;
		});
		$db->method('singleQuery')->willReturnCallback(static function(string $query) use (&$queries, $row): ?array {
			$queries[] = $query;
			return $row;
		});
		$db->method('multiQuery')->willReturnCallback(static function(string $query) use (&$queries, $rows): array {
			$queries[] = $query;
			return $rows;
		});
		$db->method('isError')->willReturn($error);
		$db->method('insertId')->willReturn(23);
		$db->method('affectedRows')->willReturn($affectedRows);
		$accesscontrol = $this->createStub(IAccesscontrol::class);
		$accesscontrol->method('getUserId')->willReturn('42');
		$session = $this->createStub(ISession::class);
		$session->method('getId')->willReturn('session-abc');
		return new AgentKnowledgeService($db, $accesscontrol, $session);
	}
}
