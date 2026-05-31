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

namespace MissionBay\Display;

final class SearchConfigDisplay extends AbstractServiceConfigDisplay {

	private const SETTINGS_GROUP = 'service-search';
	private const CONNECTION_GROUP = 'connection';
	private const SERVICE_TYPE = 'search';

	public static function getName(): string {
		return 'searchconfigdisplay';
	}

	public function getHelp(): string {
		return 'Configure web search services stored in settings group "service-search".';
	}

	protected function getSettingsGroup(): string {
		return self::SETTINGS_GROUP;
	}

	protected function getConnectionGroup(): string {
		return self::CONNECTION_GROUP;
	}

	protected function getServiceType(): string {
		return self::SERVICE_TYPE;
	}

	protected function getTemplate(): string {
		return 'Display/SearchConfigDisplay.php';
	}

	protected function getInstancePrefix(): string {
		return 'searchcfg';
	}

	protected function getListDataKey(): string {
		return 'searches';
	}

	protected function getSingleDataKey(): string {
		return 'search';
	}

	protected function getMissingIdMessage(): string {
		return 'Missing search service id.';
	}

	protected function getMissingNameMessage(): string {
		return 'Missing search service name.';
	}

	protected function getMissingDriverMessage(): string {
		return 'Missing service driver.';
	}

	protected function getUnknownDriverMessage(string $driver): string {
		return 'Unknown search service driver: ' . $driver;
	}

	protected function getMissingModelMessage(): string {
		return 'Missing model.';
	}

	protected function readSpecificOptions(array $options): array {
		$searchContextSize = trim((string)$this->request->request('searchContextSize', ''));
		$returnTokenBudget = trim((string)$this->request->request('returnTokenBudget', ''));
		$toolChoice = trim((string)$this->request->request('toolChoice', ''));
		$maxResults = $this->readOptionalInt('maxResults', 'Max results');
		$timeoutSeconds = $this->readOptionalInt('timeoutSeconds', 'Timeout seconds');
		$connectTimeoutSeconds = $this->readOptionalInt('connectTimeoutSeconds', 'Connect timeout seconds');
		$allowedDomains = $this->readDomainList('allowedDomains');
		$blockedDomains = $this->readDomainList('blockedDomains');

		if($searchContextSize !== '') {
			$options['searchContextSize'] = $searchContextSize;
		}

		$options['externalWebAccess'] = $this->normalizeBool($this->request->request('externalWebAccess', 0));

		if($returnTokenBudget !== '') {
			$options['returnTokenBudget'] = $returnTokenBudget;
		}

		if($toolChoice !== '') {
			$options['toolChoice'] = $toolChoice;
		}

		if($maxResults !== null) {
			$options['maxResults'] = $maxResults;
		}

		if($timeoutSeconds !== null) {
			$options['timeoutSeconds'] = $timeoutSeconds;
		}

		if($connectTimeoutSeconds !== null) {
			$options['connectTimeoutSeconds'] = $connectTimeoutSeconds;
		}

		$options['allowedDomains'] = $allowedDomains;
		$options['blockedDomains'] = $blockedDomains;

		return $options;
	}

	protected function expandSpecificDisplayOptions(array $row): array {
		$options = is_array($row['options'] ?? null) ? $row['options'] : [];

		$row['searchContextSize'] = trim((string)($options['searchContextSize'] ?? ''));
		$row['externalWebAccess'] = $this->normalizeBool($options['externalWebAccess'] ?? true);
		$row['returnTokenBudget'] = trim((string)($options['returnTokenBudget'] ?? ''));
		$row['toolChoice'] = trim((string)($options['toolChoice'] ?? ''));
		$row['maxResults'] = $this->normalizeNullableNumber($options['maxResults'] ?? null);
		$row['timeoutSeconds'] = $this->normalizeNullableNumber($options['timeoutSeconds'] ?? null);
		$row['connectTimeoutSeconds'] = $this->normalizeNullableNumber($options['connectTimeoutSeconds'] ?? null);
		$row['allowedDomains'] = is_array($options['allowedDomains'] ?? null) ? $options['allowedDomains'] : [];
		$row['blockedDomains'] = is_array($options['blockedDomains'] ?? null) ? $options['blockedDomains'] : [];
		$row['allowedDomainsText'] = implode("\n", $row['allowedDomains']);
		$row['blockedDomainsText'] = implode("\n", $row['blockedDomains']);

		return $row;
	}

	/**
	 * @return array<int,string>
	 */
	private function readDomainList(string $key): array {
		$raw = trim((string)$this->request->request($key, ''));

		if($raw === '') {
			return [];
		}

		$items = preg_split('/[\r\n,]+/', $raw) ?: [];
		$out = [];

		foreach($items as $item) {
			$item = trim((string)$item);

			if($item === '') {
				continue;
			}

			$item = preg_replace('#^https?://#i', '', $item) ?? $item;
			$item = preg_replace('#/.*$#', '', $item) ?? $item;
			$item = trim($item);

			if($item !== '') {
				$out[] = $item;
			}
		}

		return array_values(array_unique($out));
	}
}
