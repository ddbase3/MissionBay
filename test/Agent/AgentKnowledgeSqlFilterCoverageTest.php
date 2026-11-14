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

namespace Base3\MissionBay\Test\Agent;

use Base3\CoreFoundation\Accesscontrol\Api\IAccesscontrol;
use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\CoreFoundation\Session\Api\ISession;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AgentKnowledgeRulesProbe.php';

final class AgentKnowledgeSqlFilterCoverageTest extends TestCase {

	public function testDeletedEntriesAreExcludedByDefault(): void {
		$this->assertSame(' WHERE `is_deleted` = 0', $this->service()->whereFor([], false));
	}

	public function testExplicitlyIncludingDeletedEntriesOmitsDefaultFilter(): void {
		$this->assertSame('', $this->service()->whereFor(['include_deleted' => true], false));
	}

	public function testIdentityRestrictionDeniesAccessWithoutTrustedIdentity(): void {
		$this->assertSame(' WHERE `is_deleted` = 0 AND 1=0', $this->service()->whereFor([]));
		$this->assertSame(' WHERE 1=0', $this->service()->whereFor(['include_deleted' => true]));
	}

	public function testMemoryTypeFiltersAcceptOneOrSeveralAllowedTypes(): void {
		$service = $this->service();
		$this->assertSame(["`memory_type` = 'semantic'"], $service->quotedFilters(['memory_type' => 'semantic']));
		$this->assertSame(["`memory_type` IN ('semantic','task')"], $service->quotedFilters(['memory_type' => ['semantic', '', 'task']]));
	}

	public function testUnknownMemoryTypeIsRejectedBeforeBuildingSql(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service()->quotedFilters(['memory_type' => ['semantic', 'nonexistent']]);
	}

	public function testEmptyOptionalFiltersAreNotApplied(): void {
		$this->assertSame([], $this->service()->quotedFilters(['memory_type' => [], 'status' => [], 'memory_subtype' => [' '], 'source' => null]));
	}

	public function testMemoryKeyAndScopeReferenceNullsUseIsNull(): void {
		$clauses = $this->service()->quotedFilters(['memory_key' => null, 'scope_ref' => '', 'userid' => null, 'session' => '']);
		$this->assertSame([
			'`memory_key` IS NULL', '`scope_ref` IS NULL', '`userid` IS NULL', '`session` IS NULL'
		], $clauses);
	}

	public function testStringFiltersQuoteTheirExactFields(): void {
		$clauses = $this->service()->quotedFilters([
			'memory_key' => ' alpha ', 'memory_subtype' => 'fact', 'status' => 'valid',
			'source' => 'manual', 'scope' => 'user', 'scope_ref' => 'course',
			'ident' => 'u:1', 'userid' => '1', 'session' => 's1',
			'created_by' => 'admin', 'updated_by' => 'llm'
		]);
		$this->assertTrue(in_array("`memory_key` = 'alpha'", $clauses, true));
		$this->assertTrue(in_array("`memory_subtype` = 'fact'", $clauses, true));
		$this->assertTrue(in_array("`status` = 'valid'", $clauses, true));
		$this->assertTrue(in_array("`scope_ref` = 'course'", $clauses, true));
		$this->assertTrue(in_array("`updated_by` = 'llm'", $clauses, true));
	}

	public function testMultipleSubtypesAndStatusesUseInClauses(): void {
		$clauses = $this->service()->quotedFilters([
			'memory_subtype' => [' A ', 'B'],
			'status' => ['valid', 'draft']
		]);
		$this->assertSame(["`memory_subtype` IN ('A','B')", "`status` IN ('valid','draft')"], $clauses);
	}

	public function testSqlValueEscapingIsDelegatedToDatabaseContract(): void {
		$clauses = $this->service()->quotedFilters(['memory_key' => "alpha' OR 1=1"]);
		$this->assertSame(["`memory_key` = 'alpha\\' OR 1=1'"], $clauses);
	}

	public function testBooleanAndNumericFiltersAreNormalized(): void {
		$clauses = $this->service()->quotedFilters([
			'is_locked' => true, 'is_deleted' => false, 'min_confidence' => '0.7'
		]);
		$this->assertSame(['`is_locked` = 1', '`is_deleted` = 0', '`confidence` IS NOT NULL AND `confidence` >= 0.7'], $clauses);
	}

	public function testTagFiltersCombineAllNonEmptyTagsWithAnd(): void {
		$clauses = $this->service()->quotedFilters(['tags' => [' alpha ', '', 'beta']]);
		$this->assertCount(1, $clauses);
		$this->assertStringContainsString("`tags_json` LIKE '%alpha%'", $clauses[0]);
		$this->assertStringContainsString("AND `tags_json` LIKE '%beta%'", $clauses[0]);
	}

	public function testEntityReferenceFiltersCombineAllNonEmptyReferences(): void {
		$clauses = $this->service()->quotedFilters(['entity_refs' => [' course:1 ', '', 'doc:7']]);
		$this->assertCount(1, $clauses);
		$this->assertStringContainsString("`entity_refs_json` LIKE '%course:1%'", $clauses[0]);
		$this->assertStringContainsString("AND `entity_refs_json` LIKE '%doc:7%'", $clauses[0]);
	}

	public function testValidityAndExpirationFiltersUseInclusiveValidityAndStrictExpiration(): void {
		$clauses = $this->service()->quotedFilters(['valid_at' => '2025-05-01', 'not_expired' => true]);
		$this->assertCount(3, $clauses);
		$this->assertStringContainsString('`valid_from` <=', $clauses[0]);
		$this->assertStringContainsString('`valid_to` >=', $clauses[1]);
		$this->assertStringContainsString('`expires_at` > NOW()', $clauses[2]);
	}

	public function testInjectableFlagSqlUsesMetadataOnly(): void {
		$service = $this->service();
		$this->assertStringContainsString("JSON_EXTRACT(`meta_json`, '$.always_inject')", $service->injectCondition(true));
		$this->assertStringContainsString("JSON_EXTRACT(`meta_json`, '$.pinned')", $service->injectCondition(true));
		$this->assertStringContainsString('NOT ', $service->injectCondition(false));
	}

	public function testCuratedSqlOrdersByPinnedOrderThenPriorityRecencyAndId(): void {
		$sql = $this->service()->curatedOrderSql();
		$this->assertStringContainsString('$.inject_order', $sql);
		$this->assertStringContainsString('`priority` DESC', $sql);
		$this->assertStringContainsString('`updated_at` DESC', $sql);
		$this->assertStringContainsString('`id` DESC', $sql);
	}

	private function service(): AgentKnowledgeRulesProbe {
		$db = $this->createStub(IDatabase::class);
		$db->method('escape')->willReturnCallback(static fn(string $value): string => addslashes($value));
		return new AgentKnowledgeRulesProbe(
			$db,
			$this->createStub(IAccesscontrol::class),
			$this->createStub(ISession::class)
		);
	}
}
