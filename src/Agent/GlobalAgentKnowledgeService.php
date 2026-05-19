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

namespace MissionBay\Agent;

use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Database\Api\IDatabase;
use Base3\Session\Api\ISession;

/**
 * Knowledge service variant with a read-only global knowledge scope.
 *
 * Global entries are loaded together with the current session/user identity.
 * User entries override session entries, and both override global entries for
 * the same logical memory slot. Global entries are intentionally not writable
 * through this service; seed and maintain them through trusted SQL/import code.
 */
class GlobalAgentKnowledgeService extends AgentKnowledgeService {

	private const GLOBAL_SCOPE = 'global';
	private const GLOBAL_IDENT = 'g:global';

	public function __construct(IDatabase $db, IAccesscontrol $accesscontrol, ISession $session) {
		parent::__construct($db, $accesscontrol, $session);
	}

	public function createEntry(array $data): int {
		if($this->isGlobalScope((string)($data['scope'] ?? ''))) {
			throw new \InvalidArgumentException('Global knowledge entries must be seeded and maintained manually through a trusted admin process.');
		}

		return parent::createEntry($data);
	}

	public function updateEntry(int $id, array $data): bool {
		$entry = $this->getEntryById($id, true);

		if($entry !== null && $this->isGlobalEntry($entry)) {
			return false;
		}

		return parent::updateEntry($id, $data);
	}

	public function deleteEntry(int $id, ?string $deletedBy = null): bool {
		$entry = $this->getEntryById($id, true);

		if($entry !== null && $this->isGlobalEntry($entry)) {
			return false;
		}

		return parent::deleteEntry($id, $deletedBy);
	}

	public function touchEntry(int $id): bool {
		$entry = $this->getEntryById($id, true);

		if($entry !== null && $this->isGlobalEntry($entry)) {
			return true;
		}

		return parent::touchEntry($id);
	}

	/**
	 * Builds identity restrictions for the current identity plus global entries.
	 *
	 * @return array<int,string>
	 */
	protected function buildIdentityConditions(bool $includeSessionAndUser): array {
		$ids = $this->getCurrentIdentityRefs();
		$idents = [
			$this->quote(self::GLOBAL_IDENT)
		];

		if($ids['session_ident'] !== null && $ids['session_ident'] !== '') {
			$idents[] = $this->quote((string)$ids['session_ident']);
		}

		if($includeSessionAndUser && $ids['user_ident'] !== null && $ids['user_ident'] !== '') {
			$idents[] = $this->quote((string)$ids['user_ident']);
		}

		if(!$includeSessionAndUser && $ids['user_ident'] !== null && $ids['user_ident'] !== '') {
			$idents = [
				$this->quote(self::GLOBAL_IDENT),
				$this->quote((string)$ids['user_ident'])
			];
		}

		$idents = array_values(array_unique($idents));

		return [
			'`ident` IN (' . implode(',', $idents) . ')'
		];
	}

	/**
	 * Checks whether the current identity may access the given entry.
	 */
	protected function canAccessEntry(array $entry): bool {
		if($this->isGlobalEntry($entry)) {
			return true;
		}

		return parent::canAccessEntry($entry);
	}

	/**
	 * Returns current session/user identity references plus the global identity.
	 */
	protected function getCurrentIdentityRefs(): array {
		$ids = parent::getCurrentIdentityRefs();
		$ids['global_ident'] = self::GLOBAL_IDENT;

		return $ids;
	}

	/**
	 * User entries override session entries, and both override global entries.
	 */
	protected function shouldOverrideMergedEntry(array $existing, array $candidate): bool {
		$existingRank = $this->getScopeRank((string)($existing['scope'] ?? ''), (string)($existing['ident'] ?? ''));
		$candidateRank = $this->getScopeRank((string)($candidate['scope'] ?? ''), (string)($candidate['ident'] ?? ''));

		if($existingRank !== $candidateRank) {
			return $candidateRank > $existingRank;
		}

		$existingPriority = (int)($existing['priority'] ?? 0);
		$candidatePriority = (int)($candidate['priority'] ?? 0);

		if($candidatePriority > $existingPriority) {
			return true;
		}

		$existingUpdated = strtotime((string)($existing['updated_at'] ?? '')) ?: 0;
		$candidateUpdated = strtotime((string)($candidate['updated_at'] ?? '')) ?: 0;

		return $candidateUpdated > $existingUpdated;
	}

	private function isGlobalEntry(array $entry): bool {
		$scope = strtolower(trim((string)($entry['scope'] ?? '')));
		$ident = trim((string)($entry['ident'] ?? ''));

		return $scope === self::GLOBAL_SCOPE || $ident === self::GLOBAL_IDENT;
	}

	private function isGlobalScope(string $scope): bool {
		return strtolower(trim($scope)) === self::GLOBAL_SCOPE;
	}

	private function getScopeRank(string $scope, string $ident): int {
		$scope = strtolower(trim($scope));
		$ident = trim($ident);

		if($scope === 'user') {
			return 30;
		}

		if($scope === 'session') {
			return 20;
		}

		if($scope === self::GLOBAL_SCOPE || $ident === self::GLOBAL_IDENT) {
			return 10;
		}

		return 0;
	}
}
