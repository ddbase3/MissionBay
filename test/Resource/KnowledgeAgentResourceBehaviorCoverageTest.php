<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use MissionBay\Context\AgentContext;
use MissionBay\Resource\KnowledgeAgentResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(KnowledgeAgentResource::class)]
final class KnowledgeAgentResourceBehaviorCoverageTest extends TestCase {

	private function resource(KnowledgeAgentResourceRecordingService $service): KnowledgeAgentResource {
		return new KnowledgeAgentResource($service, 'knowledge_fixture');
	}

	private function call(KnowledgeAgentResourceRecordingService $service, string $tool, array $arguments = []): array {
		return $this->resource($service)->callTool($tool, $arguments, new AgentContext());
	}

	public function testResourceProvidesStableNameDescriptionAndReadOnlySchema(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$resource = $this->resource($service);
		$this->assertSame('knowledgeagentresource', $resource::getName());
		$this->assertSame('knowledge_fixture', $resource->getId());
		$this->assertSame('object', $resource->getSchema()['type']);
		$this->assertSame([], $resource->getSchema()['required']);
		$this->assertCount(1, $resource->getDockDefinitions());
	}

	public function testOnlySixCanonicalToolsAreAdvertised(): void {
		$definitions = $this->resource(new KnowledgeAgentResourceRecordingService())->getToolDefinitions();
		$this->assertSame([
			'knowledge_memory_search', 'knowledge_memory_get_entry', 'knowledge_memory_upsert',
			'knowledge_memory_update', 'knowledge_memory_delete_entry', 'procedural_memory_get_applicable'
		], array_column(array_column($definitions, 'function'), 'name'));
	}

	public function testReadOnlyDefinitionsDoNotAdvertiseMutations(): void {
		$definitions = $this->resource(new KnowledgeAgentResourceRecordingService())->getToolDefinitions();
		foreach ([0, 1, 5] as $index) {
			$this->assertTrue($definitions[$index]['readOnlyHint']);
			$this->assertSame(['knowledge', 'memory', 'skills', 'readonly'], $definitions[$index]['tags']);
		}
	}

	public function testWriteDefinitionsAreMarkedInternalAndNotCacheable(): void {
		$definitions = $this->resource(new KnowledgeAgentResourceRecordingService())->getToolDefinitions();
		foreach ([2, 3, 4] as $index) {
			$this->assertFalse($definitions[$index]['readOnlyHint']);
			$this->assertTrue($definitions[$index]['mutation']);
			$this->assertTrue($definitions[$index]['internalStateWrite']);
			$this->assertFalse($definitions[$index]['cacheable']);
			$this->assertFalse($definitions[$index]['commitGuardRequired']);
		}
	}

	public function testCanonicalDefinitionsDescribeRequiredInputs(): void {
		$definitions = $this->resource(new KnowledgeAgentResourceRecordingService())->getToolDefinitions();
		$this->assertSame([], $definitions[0]['function']['parameters']['required']);
		$this->assertSame(['id'], $definitions[1]['function']['parameters']['required']);
		$this->assertSame(['memory_type', 'title'], $definitions[2]['function']['parameters']['required']);
		$this->assertSame(['id'], $definitions[3]['function']['parameters']['required']);
		$this->assertSame(['query'], $definitions[5]['function']['parameters']['required']);
	}

	public function testLegacyToolNamesAreNotInCanonicalDefinitions(): void {
		$names = array_column(array_column($this->resource(new KnowledgeAgentResourceRecordingService())->getToolDefinitions(), 'function'), 'name');
		$this->assertFalse(in_array('task_memory_create', $names, true));
		$this->assertFalse(in_array('knowledge_memory_list_types', $names, true));
	}

	public function testUnknownToolFailsWithoutDelegating(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->call(new KnowledgeAgentResourceRecordingService(), 'unknown_tool');
	}

	public function testLegacyListTypesReturnsCatalog(): void {
		$result = $this->call(new KnowledgeAgentResourceRecordingService(), 'knowledge_memory_list_types');
		$this->assertSame(4, $result['count']);
		$this->assertSame('semantic', $result['types'][2]);
	}

	public function testLegacyListStatusesReturnsAllTypes(): void {
		$result = $this->call(new KnowledgeAgentResourceRecordingService(), 'knowledge_memory_list_statuses');
		$this->assertSame(4, $result['count']);
		$this->assertSame(['active'], $result['statuses_by_type']['procedural']);
	}

	public function testLegacyListStatusesFiltersOneType(): void {
		$result = $this->call(new KnowledgeAgentResourceRecordingService(), 'knowledge_memory_list_statuses', ['memory_type' => ' semantic ']);
		$this->assertSame('semantic', $result['memory_type']);
		$this->assertSame(1, $result['count']);
		$this->assertSame(['valid'], $result['statuses']);
	}

	public function testGetRejectsMissingId(): void {
		$this->assertSame(['error' => 'Missing or invalid parameter: id'], $this->call(new KnowledgeAgentResourceRecordingService(), 'knowledge_memory_get_entry'));
	}

	public function testGetRejectsNegativeId(): void {
		$this->assertSame(['error' => 'Missing or invalid parameter: id'], $this->call(new KnowledgeAgentResourceRecordingService(), 'knowledge_memory_get_entry', ['id' => -3]));
	}

	public function testGetReturnsNotFoundWithoutTouch(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$this->assertSame(['error' => 'Knowledge entry not found'], $this->call($service, 'knowledge_memory_get_entry', ['id' => 20]));
		$this->assertSame([], $service->touched);
	}

	public function testGetReturnsEntryAndUpdatesAccessTimestamp(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$service->entries[7] = ['id' => 7, 'title' => 'Runbook'];
		$result = $this->call($service, 'knowledge_memory_get_entry', ['id' => 7]);
		$this->assertTrue($result['ok']);
		$this->assertSame('Runbook', $result['entry']['title']);
		$this->assertSame([7], $service->touched);
	}

	public function testDeleteRejectsZeroId(): void {
		$this->assertSame(['error' => 'Missing or invalid parameter: id'], $this->call(new KnowledgeAgentResourceRecordingService(), 'knowledge_memory_delete_entry', ['id' => 0]));
	}

	public function testDeleteDelegatesLlmActor(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$result = $this->call($service, 'knowledge_memory_delete_entry', ['id' => 8]);
		$this->assertTrue($result['ok']);
		$this->assertTrue($result['deleted']);
		$this->assertSame([[8, 'llm']], $service->deleted);
	}

	public function testDeleteReportsRefusalWithoutClaimingDeletion(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$service->deleteSuccess = false;
		$result = $this->call($service, 'knowledge_memory_delete_entry', ['id' => 8]);
		$this->assertFalse($result['deleted']);
		$this->assertSame([[8, 'llm']], $service->deleted);
	}

	public function testSearchWithoutQueryUsesUnexpiredFilter(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$result = $this->call($service, 'knowledge_memory_search');
		$this->assertSame(0, $result['count']);
		$this->assertSame(['', ['not_expired' => true], 10, 0], $service->searchCalls[0]);
	}

	public function testSearchTrimsQueryAndClampsLargeLimit(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$result = $this->call($service, 'knowledge_memory_search', ['query' => '  deployment  ', 'limit' => 900]);
		$this->assertSame('deployment', $result['query']);
		$this->assertSame(['deployment', ['not_expired' => true], 50, 0], $service->searchCalls[0]);
	}

	public function testSearchClampsNegativeLimitToOne(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$this->call($service, 'knowledge_memory_search', ['limit' => -5]);
		$this->assertSame(1, $service->searchCalls[0][2]);
	}

	public function testSearchNormalizesFiltersAndDeduplicatesLists(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$this->call($service, 'knowledge_memory_search', [
			'memory_type' => ' semantic ', 'status' => '  valid ', 'scope_ref' => 'team:1',
			'tags' => ' one, two, one, ', 'entity_refs' => ['item:1', 'item:1', ' ', 'item:2'],
			'not_expired' => 'off'
		]);
		$this->assertSame([
			'memory_type' => 'semantic', 'status' => 'valid', 'scope_ref' => 'team:1',
			'tags' => ['one', 'two'], 'entity_refs' => ['item:1', 'item:2'], 'not_expired' => false
		], $service->searchCalls[0][1]);
	}

	public function testSearchAcceptsExplicitNullScopeAndFalseExpiryFlag(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$this->call($service, 'knowledge_memory_search', ['scope_ref' => null, 'not_expired' => 0]);
		$this->assertSame(['scope_ref' => null, 'not_expired' => false], $service->searchCalls[0][1]);
	}

	public function testSearchTouchesOnlyEntriesWithIds(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$service->searchResults = [['id' => 5], ['id' => null], ['title' => 'anonymous'], ['id' => 9]];
		$result = $this->call($service, 'knowledge_memory_search', ['query' => 'how']);
		$this->assertSame(4, $result['count']);
		$this->assertSame([5, 9], $service->touched);
	}

	public function testLegacyTypedSearchIgnoresForeignRequestedType(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$result = $this->call($service, 'task_memory_search', ['memory_type' => 'semantic', 'query' => ' list ']);
		$this->assertSame('task', $result['memory_type']);
		$this->assertSame(['list', ['memory_type' => 'task', 'not_expired' => true], 10, 0], $service->searchCalls[0]);
	}

	public function testApplicableProcedureRequiresQuery(): void {
		$this->assertSame(['error' => 'Missing parameter: query'], $this->call(new KnowledgeAgentResourceRecordingService(), 'procedural_memory_get_applicable'));
	}

	public function testApplicableProcedureEnforcesActiveAndUnexpiredScope(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$this->call($service, 'procedural_memory_get_applicable', ['query' => '  backup  ', 'scope_ref' => 'a', 'limit' => 800, 'tags' => ' ops,ops ']);
		$this->assertSame(['backup', ['memory_type' => 'procedural', 'status' => 'active', 'not_expired' => true, 'scope_ref' => 'a', 'tags' => ['ops']], 20, 0], $service->searchCalls[0]);
	}

	public function testApplicableProcedureClampsLimitToOne(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$this->call($service, 'procedural_memory_get_applicable', ['query' => 'audit', 'limit' => 0]);
		$this->assertSame(1, $service->searchCalls[0][2]);
	}

	public function testApplicableProcedureTouchesMatchingEntries(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$service->searchResults = [['id' => 33, 'title' => 'Deploy'], ['title' => 'Without id']];
		$result = $this->call($service, 'procedural_memory_get_applicable', ['query' => 'deploy']);
		$this->assertSame(2, $result['count']);
		$this->assertSame(2, count($result['applicable']));
		$this->assertSame([33], $service->touched);
	}

	public function testCreateRequiresTitleAndContent(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$this->assertSame(['error' => 'Missing parameter: title'], $this->call($service, 'task_memory_create', ['content' => 'text']));
		$this->assertSame(['error' => 'Missing parameter: content'], $this->call($service, 'task_memory_create', ['title' => 'record']));
		$this->assertSame([], $service->created);
	}

	public function testCreateAssignsTypeDefaultStatusAndLlmAttribution(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$result = $this->call($service, 'semantic_memory_create', ['title' => '  Fact  ', 'content' => ' Evidence ']);
		$this->assertSame('created', $result['action']);
		$this->assertSame('semantic', $service->created[0]['memory_type']);
		$this->assertSame('valid', $service->created[0]['status']);
		$this->assertSame('Fact', $service->created[0]['title']);
		$this->assertSame('Evidence', $service->created[0]['content']);
		$this->assertSame('llm', $service->created[0]['created_by']);
		$this->assertSame('llm', $service->created[0]['updated_by']);
	}

	public function testCreatePreservesExplicitStatusAndConvertsBooleanFlags(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$this->call($service, 'task_memory_create', [
			'title' => 'Work', 'content' => 'Do work', 'status' => 'closed',
			'is_locked' => 'on', 'is_mutable_by_llm' => 'no', 'is_deletable_by_llm' => 0
		]);
		$this->assertSame('closed', $service->created[0]['status']);
		$this->assertTrue($service->created[0]['is_locked']);
		$this->assertFalse($service->created[0]['is_mutable_by_llm']);
		$this->assertFalse($service->created[0]['is_deletable_by_llm']);
	}

	public function testCreateBuildsNormalizedTagsAndMetadata(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$this->call($service, 'procedural_memory_create', [
			'title' => 'Procedure', 'content' => 'Steps', 'tags' => ' one,one, two ',
			'entity_refs' => ['doc:1', 'doc:1', 'doc:2'], 'meta' => ['custom' => 'yes'],
			'always_inject' => 'yes', 'inject_order' => '3', 'inject_group' => ' ops '
		]);
		$this->assertSame(['one', 'two'], $service->created[0]['tags_json']);
		$this->assertSame(['doc:1', 'doc:2'], $service->created[0]['entity_refs_json']);
		$this->assertSame(['custom' => 'yes', 'always_inject' => true, 'inject_order' => 3, 'inject_group' => 'ops'], $service->created[0]['meta_json']);
	}

	public function testCreateNormalizesScopeDatesAndNumerics(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$this->call($service, 'episodic_memory_create', [
			'title' => 'Event', 'content' => 'Observed', 'memory_key' => '  key  ', 'scope' => ' global ',
			'scope_ref' => ' team ', 'priority' => '4', 'confidence' => '0.75',
			'valid_from' => ' 2026-01-01 ', 'valid_to' => null, 'expires_at' => ' 2027-01-01 '
		]);
		$this->assertSame('key', $service->created[0]['memory_key']);
		$this->assertSame('global', $service->created[0]['scope']);
		$this->assertSame('team', $service->created[0]['scope_ref']);
		$this->assertSame(4, $service->created[0]['priority']);
		$this->assertSame(0.75, $service->created[0]['confidence']);
		$this->assertNull($service->created[0]['valid_to']);
	}

	public function testCreateReturnsStructuredErrorWhenStorageRejectsWrite(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$service->throwOnCreate = true;
		$result = $this->call($service, 'task_memory_create', ['title' => 'Task', 'content' => 'Work']);
		$this->assertSame('Failed to create knowledge entry', $result['error']);
		$this->assertSame('Write failed', $result['details']);
	}

	public function testUpdateRejectsMissingOrUnknownId(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$this->assertSame(['error' => 'Missing or invalid parameter: id'], $this->call($service, 'knowledge_memory_update'));
		$this->assertSame(['error' => 'Knowledge entry not found'], $this->call($service, 'knowledge_memory_update', ['id' => 12]));
	}

	public function testUpdateRejectsUnsupportedStoredMemoryType(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$service->entries[4] = ['id' => 4, 'memory_type' => 'unknown'];
		$this->assertSame(['error' => 'Knowledge entry has an unsupported memory type'], $this->call($service, 'knowledge_memory_update', ['id' => 4, 'title' => 'new']));
	}

	public function testTypedUpdateRejectsWrongStoredMemoryType(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$service->entries[4] = ['id' => 4, 'memory_type' => 'semantic'];
		$this->assertSame(['error' => 'Knowledge entry does not match required memory type'], $this->call($service, 'task_memory_update', ['id' => 4]));
	}

	public function testUpdateMergesExistingMetadataWithoutReplacingUnspecifiedKeys(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$service->entries[4] = ['id' => 4, 'memory_type' => 'semantic', 'meta_json' => ['retain' => true, 'change' => 1]];
		$result = $this->call($service, 'knowledge_memory_update', ['id' => 4, 'meta' => ['change' => 2]]);
		$this->assertSame('updated', $result['action']);
		$this->assertSame(['retain' => true, 'change' => 2], $service->updated[4]['meta_json']);
		$this->assertSame('llm', $service->updated[4]['updated_by']);
	}

	public function testUpdateDoesNotOverwriteScopeWhenCreatingIsFalse(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$service->entries[4] = ['id' => 4, 'memory_type' => 'task'];
		$this->call($service, 'task_memory_update', ['id' => 4, 'scope' => 'global', 'summary' => ' summary ']);
		$this->assertArrayNotHasKey('scope', $service->updated[4]);
		$this->assertSame('summary', $service->updated[4]['summary']);
	}

	public function testUpdateReportsUnchangedEntry(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$service->entries[4] = ['id' => 4, 'memory_type' => 'task'];
		$service->updateSuccess = false;
		$result = $this->call($service, 'task_memory_update', ['id' => 4, 'title' => 'same']);
		$this->assertFalse($result['updated']);
		$this->assertSame('updated', $result['action']);
	}

	public function testUpsertRequiresSupportedMemoryType(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$this->assertSame(['error' => 'Missing or invalid parameter: memory_type'], $this->call($service, 'knowledge_memory_upsert', ['memory_type' => 'unknown', 'title' => 'x']));
	}

	public function testUpsertNormalizesMemoryType(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$result = $this->call($service, 'knowledge_memory_upsert', ['memory_type' => ' SEMANTIC ', 'title' => 'Fact', 'content' => 'known']);
		$this->assertSame('created', $result['action']);
		$this->assertSame('semantic', $service->created[0]['memory_type']);
	}

	public function testUpsertRequiresTitleAndContentWhenNoMatchExists(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$this->assertSame(['error' => 'Missing parameter: title'], $this->call($service, 'task_memory_upsert', ['content' => 'body']));
		$this->assertSame(['error' => 'Missing parameter: content (required when no existing entry is found)'], $this->call($service, 'task_memory_upsert', ['title' => 'title']));
	}

	public function testUpsertMatchesExistingByStableKeyBeforeTitle(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$service->entries[14] = ['id' => 14, 'memory_type' => 'semantic', 'title' => 'Old'];
		$service->findResponses = [[['id' => 14, 'title' => 'Old']]];
		$result = $this->call($service, 'semantic_memory_upsert', ['memory_key' => 'same', 'title' => 'Different', 'content' => 'new']);
		$this->assertSame('updated', $result['action']);
		$this->assertSame(14, $result['matched_entry_id']);
		$this->assertSame(['memory_type' => 'semantic', 'memory_key' => 'same'], $service->findCalls[0][0]);
		$this->assertSame([], $service->created);
	}

	public function testUpsertFallsBackToNormalizedTitleWhenKeyNotFound(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$service->entries[14] = ['id' => 14, 'memory_type' => 'task', 'title' => 'Deploy'];
		$service->findResponses = [[], [['id' => 14, 'title' => ' DEPLOY ']]];
		$result = $this->call($service, 'task_memory_upsert', ['memory_key' => 'not-existing', 'title' => 'deploy', 'content' => 'new', 'memory_subtype' => 'checklist']);
		$this->assertSame('updated', $result['action']);
		$this->assertSame(['memory_type' => 'task', 'memory_subtype' => 'checklist'], $service->findCalls[1][0]);
	}

	public function testUpsertIgnoresUnmatchedTitles(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$service->findResponses = [[['id' => 7, 'title' => 'Other']]];
		$result = $this->call($service, 'semantic_memory_upsert', ['title' => 'Different', 'content' => 'new']);
		$this->assertSame('created', $result['action']);
		$this->assertCount(1, $service->created);
	}

	public function testUpsertMayUpdateWithoutNewContentWhenEntryExists(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$service->entries[8] = ['id' => 8, 'memory_type' => 'procedural', 'title' => 'Guide'];
		$service->findResponses = [[['id' => 8, 'title' => 'Guide']]];
		$result = $this->call($service, 'procedural_memory_upsert', ['title' => 'Guide', 'summary' => 'new']);
		$this->assertSame('updated', $result['action']);
		$this->assertSame('new', $service->updated[8]['summary']);
	}

	public function testUpsertRetainsExplicitNullScopeReference(): void {
		$service = new KnowledgeAgentResourceRecordingService();
		$service->entries[8] = ['id' => 8, 'memory_type' => 'task', 'title' => 'Guide'];
		$service->findResponses = [[['id' => 8, 'title' => 'Guide']]];
		$this->call($service, 'task_memory_upsert', ['title' => 'Guide', 'scope_ref' => null]);
		$this->assertArrayNotHasKey('scope_ref', $service->findCalls[0][0]);
	}
}
