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

use Base3\MissionBay\Agent\AgentKnowledgeService;

final class AgentKnowledgeRulesProbe extends AgentKnowledgeService {

	public function searchCandidateFilters(array $options): array {
		return $this->buildSearchCandidateFilters($options);
	}

	public function searchFallbackProfiles(array $filters): array {
		return $this->buildSearchFallbackProfiles($filters);
	}

	public function candidateLimit(int $limit, int $offset): int {
		return $this->resolveSearchCandidateLimit($limit, $offset);
	}

	public function rankedEntries(array $entries, string $query): array {
		return $this->rankSearchEntries($entries, $query);
	}

	public function scoreFor(array $entry, array $tokens): int {
		return $this->getSearchQueryScore($entry, $tokens);
	}

	public function tokensFor(string $query): array {
		return $this->tokenizeSearchQuery($query);
	}

	public function searchString(mixed $value): string {
		return $this->stringifySearchValue($value);
	}

	public function mergedEntries(array $rows): array {
		return $this->mergeIdentityRows($rows);
	}

	public function curatedEntries(array $rows): array {
		return $this->sortCuratedEntries($rows);
	}

	public function hydratedEntry(array $row): array {
		return $this->hydrateRow($row);
	}

	public function normalizedKey(string $value): string {
		return $this->normalizeKeyPart($value);
	}

	public function truncateForPrompt(string $text, int $length): string {
		return $this->truncate($text, $length);
	}

	public function boolForSql(mixed $value): bool {
		return $this->toSqlBool($value);
	}

	public function quotedFilters(array $filters): array {
		return $this->buildFilterConditions($filters);
	}

	public function whereFor(array $filters, bool $identity = true): string {
		return $this->buildWhereClause($filters, $identity);
	}

	public function identityConditions(bool $both): array {
		return $this->buildIdentityConditions($both);
	}

	public function identityRefs(): array {
		return $this->getCurrentIdentityRefs();
	}

	public function scopeFor(string $requested): string {
		return $this->resolveFinalScope($requested);
	}

	public function scopeIdsFor(string $scope): array {
		return $this->resolveScopeIds($scope);
	}

	public function accessFor(array $entry): bool {
		return $this->canAccessEntry($entry);
	}

	public function injectCondition(bool $flag): string {
		return $this->buildInjectFlagSqlCondition($flag);
	}

	public function curatedOrderSql(): string {
		return $this->buildCuratedOrderSql();
	}
}
