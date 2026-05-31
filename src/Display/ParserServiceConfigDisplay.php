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

final class ParserServiceConfigDisplay extends AbstractServiceConfigDisplay {

	private const SETTINGS_GROUP = 'service-parser';
	private const CONNECTION_GROUP = 'connection';
	private const SERVICE_TYPE = 'parser';

	public static function getName(): string {
		return 'parserserviceconfigdisplay';
	}

	public function getHelp(): string {
		return 'Configure parsers stored in settings group "service-parser".';
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
		return 'Display/ParserServiceConfigDisplay.php';
	}

	protected function getInstancePrefix(): string {
		return 'parsercfg';
	}

	protected function getListDataKey(): string {
		return 'parsers';
	}

	protected function getSingleDataKey(): string {
		return 'parser';
	}

	protected function getMissingIdMessage(): string {
		return 'Missing parser id.';
	}

	protected function getMissingNameMessage(): string {
		return 'Missing parser name.';
	}

	protected function getMissingDriverMessage(): string {
		return 'Missing parser driver.';
	}

	protected function getUnknownDriverMessage(string $driver): string {
		return 'Unknown parser driver: ' . $driver;
	}

	protected function getMissingModelMessage(): string {
		return 'Missing internal parser engine.';
	}

	protected function readSpecificOptions(array $options): array {
		$contentType = trim((string)$this->request->request('contentType', ''));
		$fileField = trim((string)$this->request->request('fileField', ''));
		$supportedTypes = $this->readStringList('supportedTypes', true);
		$priority = $this->readOptionalInt('priority', 'Priority');
		$timeoutSeconds = $this->readOptionalInt('timeoutSeconds', 'Timeout seconds');
		$connectTimeoutSeconds = $this->readOptionalInt('connectTimeoutSeconds', 'Connect timeout seconds');
		$maxBytes = $this->readOptionalInt('maxBytes', 'Max bytes');

		if($contentType !== '') {
			$options['contentType'] = $contentType;
		}

		if($supportedTypes !== []) {
			$options['supportedTypes'] = $supportedTypes;
		}

		if($priority !== null) {
			$options['priority'] = $priority;
		}

		if($fileField !== '') {
			$options['fileField'] = $fileField;
		}

		if($timeoutSeconds !== null) {
			$options['timeoutSeconds'] = $timeoutSeconds;
		}

		if($connectTimeoutSeconds !== null) {
			$options['connectTimeoutSeconds'] = $connectTimeoutSeconds;
		}

		if($maxBytes !== null) {
			$options['maxBytes'] = $maxBytes;
		}

		return $options;
	}

	protected function expandSpecificDisplayOptions(array $row): array {
		$options = is_array($row['options'] ?? null) ? $row['options'] : [];

		$row['contentType'] = trim((string)($options['contentType'] ?? ''));
		$row['supportedTypes'] = is_array($options['supportedTypes'] ?? null) ? $options['supportedTypes'] : [];
		$row['supportedTypesText'] = implode("\n", $row['supportedTypes']);
		$row['priority'] = $this->normalizeNullableNumber($options['priority'] ?? null);
		$row['fileField'] = trim((string)($options['fileField'] ?? ''));
		$row['timeoutSeconds'] = $this->normalizeNullableNumber($options['timeoutSeconds'] ?? null);
		$row['connectTimeoutSeconds'] = $this->normalizeNullableNumber($options['connectTimeoutSeconds'] ?? null);
		$row['maxBytes'] = $this->normalizeNullableNumber($options['maxBytes'] ?? null);

		return $row;
	}

	/**
	 * @return array<int,string>
	 */
	private function readStringList(string $key, bool $lowercase): array {
		$raw = trim((string)$this->request->request($key, ''));

		if($raw === '') {
			return [];
		}

		$items = preg_split('/[\r\n,]+/', $raw) ?: [];
		$out = [];

		foreach($items as $item) {
			$item = trim((string)$item);

			if($lowercase) {
				$item = strtolower($item);
			}

			if($item !== '') {
				$out[] = $item;
			}
		}

		return array_values(array_unique($out));
	}
}
