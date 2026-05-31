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

final class VectorStoreConfigDisplay extends AbstractServiceConfigDisplay {

	private const SETTINGS_GROUP = 'service-vectorstore';
	private const CONNECTION_GROUP = 'connection';
	private const SERVICE_TYPE = 'vectorstore';

	public static function getName(): string {
		return 'vectorstoreconfigdisplay';
	}

	public function getHelp(): string {
		return 'Configure vector stores stored in settings group "service-vectorstore".';
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
		return 'Display/VectorStoreConfigDisplay.php';
	}

	protected function getInstancePrefix(): string {
		return 'vectorstorecfg';
	}

	protected function getListDataKey(): string {
		return 'vectorstores';
	}

	protected function getSingleDataKey(): string {
		return 'vectorstore';
	}

	protected function getMissingIdMessage(): string {
		return 'Missing vector store id.';
	}

	protected function getMissingNameMessage(): string {
		return 'Missing vector store name.';
	}

	protected function getMissingDriverMessage(): string {
		return 'Missing vector store driver.';
	}

	protected function getUnknownDriverMessage(string $driver): string {
		return 'Unknown vector store driver: ' . $driver;
	}

	protected function getMissingModelMessage(): string {
		return 'Missing internal vector store engine.';
	}

	protected function readSpecificOptions(array $options): array {
		$createPayloadIndexes = $this->normalizeBool($this->request->request('createPayloadIndexes', 0));
		$timeoutSeconds = $this->readOptionalInt('timeoutSeconds', 'Timeout seconds');
		$connectTimeoutSeconds = $this->readOptionalInt('connectTimeoutSeconds', 'Connect timeout seconds');

		$options['createPayloadIndexes'] = $createPayloadIndexes;

		if($timeoutSeconds !== null) {
			$options['timeoutSeconds'] = $timeoutSeconds;
		}

		if($connectTimeoutSeconds !== null) {
			$options['connectTimeoutSeconds'] = $connectTimeoutSeconds;
		}

		return $options;
	}

	protected function expandSpecificDisplayOptions(array $row): array {
		$options = is_array($row['options'] ?? null) ? $row['options'] : [];

		$row['createPayloadIndexes'] = $this->normalizeBool($options['createPayloadIndexes'] ?? true);
		$row['timeoutSeconds'] = $this->normalizeNullableNumber($options['timeoutSeconds'] ?? null);
		$row['connectTimeoutSeconds'] = $this->normalizeNullableNumber($options['connectTimeoutSeconds'] ?? null);

		return $row;
	}
}
