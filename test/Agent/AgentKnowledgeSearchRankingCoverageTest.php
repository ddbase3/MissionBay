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

final class AgentKnowledgeSearchRankingCoverageTest extends TestCase {

	public function testEmptyCandidateListStaysEmpty(): void {
		$this->assertSame([], $this->service()->rankedEntries([], 'hello'));
	}

	public function testCandidateLimitsRemainBoundedForSmallAndLargePages(): void {
		$service = $this->service();
		$this->assertSame(50, $service->candidateLimit(1, 0));
		$this->assertSame(75, $service->candidateLimit(10, 5));
		$this->assertSame(100, $service->candidateLimit(100, 100));
		$this->assertSame(50, $service->candidateLimit(-50, 0));
	}

	public function testSearchFiltersTrimAndDeduplicateTagsAndReferences(): void {
		$filters = $this->service()->searchCandidateFilters([
			'memory_type' => 'semantic',
			'status' => 'valid',
			'scope_ref' => 'course-3',
			'tags' => ['  alpha ', '', 'alpha', ' beta'],
			'entity_refs' => [' user:1 ', 'user:1', ''],
			'not_expired' => 1
		]);
		$this->assertSame(['alpha', 'beta'], $filters['tags']);
		$this->assertSame(['user:1'], $filters['entity_refs']);
		$this->assertSame('semantic', $filters['memory_type']);
		$this->assertTrue($filters['not_expired']);
	}

	public function testSearchFiltersIgnoreUnsupportedAndEmptySoftFilters(): void {
		$this->assertSame([], $this->service()->searchCandidateFilters([
			'memory_type' => '', 'status' => null, 'tags' => ['', ' '],
			'entity_refs' => [''], 'unknown' => 'never'
		]));
	}

	public function testFallbackProfilesRelaxSoftFiltersAndKeepExpirationGuard(): void {
		$profiles = $this->service()->searchFallbackProfiles([
			'memory_type' => 'semantic', 'status' => 'valid', 'scope_ref' => 'lesson',
			'tags' => ['alpha'], 'entity_refs' => ['id-1'], 'not_expired' => true
		]);
		$this->assertSame(['alpha'], $profiles[0]['tags']);
		$this->assertArrayNotHasKey('tags', $profiles[1]);
		$this->assertArrayNotHasKey('entity_refs', $profiles[1]);
		$this->assertArrayNotHasKey('scope_ref', $profiles[2]);
		$this->assertArrayNotHasKey('status', $profiles[3]);
		$this->assertSame(['memory_type' => 'semantic', 'not_expired' => true], $profiles[3]);
		$this->assertSame(['not_expired' => true], $profiles[4]);
	}

	public function testFallbackProfilesDoNotDuplicateIdenticalFilterSets(): void {
		$this->assertSame([[]], $this->service()->searchFallbackProfiles([]));
		$this->assertSame([['not_expired' => true]], $this->service()->searchFallbackProfiles(['not_expired' => true]));
	}

	public function testSearchTokensNormalizePunctuationAndRemoveShortNoise(): void {
		$this->assertSame(['base3', 'tool', '7'], $this->service()->tokensFor(' a BASE3, tool! tool 7 b'));
		$this->assertSame([], $this->service()->tokensFor('a - _'));
	}

	public function testSearchTextFlattensNestedTagsAndReferences(): void {
		$this->assertSame('alpha beta id 7', $this->service()->searchString(['Alpha', ['Beta', 'id', 7], null, '']));
		$this->assertSame('', $this->service()->searchString(null));
		$this->assertSame('', $this->service()->searchString('  '));
	}

	public function testSearchScoreWeightsKnowledgeKeysAboveBodyMatches(): void {
		$service = $this->service();
		$this->assertSame(18, $service->scoreFor(['memory_key' => 'alpha'], ['alpha']));
		$this->assertSame(13, $service->scoreFor(['title' => 'alpha'], ['alpha']));
		$this->assertSame(9, $service->scoreFor(['summary' => 'alpha'], ['alpha']));
		$this->assertSame(3, $service->scoreFor(['content' => 'alpha'], ['alpha']));
	}

	public function testSearchScoreIncludesMetadataTagsAndEntityReferences(): void {
		$service = $this->service();
		$this->assertSame(5, $service->scoreFor(['tags_json' => ['alpha']], ['alpha']));
		$this->assertSame(5, $service->scoreFor(['entity_refs_json' => ['alpha']], ['alpha']));
		$this->assertSame(4, $service->scoreFor(['memory_subtype' => 'alpha'], ['alpha']));
		$this->assertSame(3, $service->scoreFor(['scope_ref' => 'alpha'], ['alpha']));
	}

	public function testSearchScoreRecognizesMultipleTokensAndExactPhrases(): void {
		$service = $this->service();
		$this->assertSame(20, $service->scoreFor(['title' => 'alpha beta'], ['alpha', 'beta']));
		$this->assertSame(0, $service->scoreFor(['title' => 'something else'], ['alpha']));
	}

	public function testMatchesArePreferredOverUnmatchedHighPriorityCandidates(): void {
		$entries = [
			['id' => 1, 'title' => 'unrelated', 'priority' => 100],
			['id' => 2, 'title' => 'alpha topic', 'priority' => 1]
		];
		$ranked = $this->service()->rankedEntries($entries, 'alpha');
		$this->assertSame([2, 1], array_column($ranked, 'id'));
		$this->assertArrayNotHasKey('_search_score', $ranked[0]);
	}

	public function testHigherPriorityWinsAmongMatchingCandidates(): void {
		$entries = [
			['id' => 1, 'title' => 'alpha', 'priority' => 1],
			['id' => 2, 'content' => 'alpha', 'priority' => 3]
		];
		$this->assertSame([2, 1], array_column($this->service()->rankedEntries($entries, 'alpha'), 'id'));
	}

	public function testHigherScoreWinsAtEqualPriority(): void {
		$entries = [
			['id' => 1, 'content' => 'alpha', 'priority' => 5],
			['id' => 2, 'title' => 'alpha', 'priority' => 5]
		];
		$this->assertSame([2, 1], array_column($this->service()->rankedEntries($entries, 'alpha'), 'id'));
	}

	public function testFallbackCandidatesPreservePriorityAndRecencyWhenNothingMatches(): void {
		$entries = [
			['id' => 1, 'title' => 'a', 'priority' => 1, 'updated_at' => '2026-01-01 00:00:00'],
			['id' => 2, 'title' => 'b', 'priority' => 3, 'updated_at' => '2025-01-01 00:00:00'],
			['id' => 3, 'title' => 'c', 'priority' => 1, 'updated_at' => '2026-02-01 00:00:00']
		];
		$this->assertSame([2, 3, 1], array_column($this->service()->rankedEntries($entries, 'zzz'), 'id'));
	}

	public function testEmptyQueryUsesBroadOrderingWithoutSearchMetadata(): void {
		$entries = [
			['id' => 1, 'priority' => 1],
			['id' => 2, 'priority' => 5]
		];
		$ranked = $this->service()->rankedEntries($entries, ' ');
		$this->assertSame([2, 1], array_column($ranked, 'id'));
		$this->assertArrayNotHasKey('_search_score', $ranked[0]);
	}

	public function testCuratedEntriesOrderInjectionIndexBeforePriority(): void {
		$entries = [
			['id' => 1, 'priority' => 100, 'meta_json' => ['inject_order' => 20]],
			['id' => 2, 'priority' => 1, 'meta_json' => ['inject_order' => 10]],
			['id' => 3, 'priority' => 10, 'meta_json' => ['inject_order' => 10]]
		];
		$this->assertSame([3, 2, 1], array_column($this->service()->curatedEntries($entries), 'id'));
	}

	public function testCuratedEntriesUseRecencyThenIdAsStableTieBreakers(): void {
		$entries = [
			['id' => 1, 'priority' => 2, 'updated_at' => '2026-01-01'],
			['id' => 2, 'priority' => 2, 'updated_at' => '2026-02-01'],
			['id' => 3, 'priority' => 2, 'updated_at' => '2026-02-01']
		];
		$this->assertSame([3, 2, 1], array_column($this->service()->curatedEntries($entries), 'id'));
	}

	private function service(): AgentKnowledgeRulesProbe {
		return new AgentKnowledgeRulesProbe(
			$this->createStub(IDatabase::class),
			$this->createStub(IAccesscontrol::class),
			$this->createStub(ISession::class)
		);
	}
}
