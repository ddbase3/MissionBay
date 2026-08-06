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

final class VectorSearchConfigDisplay extends AbstractServiceConfigDisplay {

	private const SETTINGS_GROUP = 'service-vectorsearch';
	private const CONNECTION_GROUP = 'connection';
	private const SERVICE_TYPE = 'vectorsearch';

	public static function getName(): string {
		return 'vectorsearchconfigdisplay';
	}

	public function getHelp(): string {
		return 'Configure vector-search services stored in settings group "service-vectorsearch".';
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
		return 'Display/VectorSearchConfigDisplay.php';
	}

	protected function getInstancePrefix(): string {
		return 'vectorsearchcfg';
	}

	protected function getListDataKey(): string {
		return 'vectorsearches';
	}

	protected function getSingleDataKey(): string {
		return 'vectorsearch';
	}

	protected function getMissingIdMessage(): string {
		return 'Missing vector-search id.';
	}

	protected function getMissingNameMessage(): string {
		return 'Missing vector-search name.';
	}

	protected function getMissingDriverMessage(): string {
		return 'Missing vector-search driver.';
	}

	protected function getUnknownDriverMessage(string $driver): string {
		return 'Unknown vector-search driver: ' . $driver;
	}

	protected function getMissingModelMessage(): string {
		return 'Missing vector-search engine.';
	}

	protected function readSpecificOptions(array $options): array {
		$collection = trim((string)$this->request->request('collection', ''));
		if($collection === '') {
			throw new \RuntimeException('Missing vector-search collection.');
		}

		$options['collection'] = $collection;

		return $options;
	}

	protected function expandSpecificDisplayOptions(array $row): array {
		$options = is_array($row['options'] ?? null) ? $row['options'] : [];

		$row['collection'] = trim((string)($options['collection'] ?? ''));

		return $row;
	}
}
