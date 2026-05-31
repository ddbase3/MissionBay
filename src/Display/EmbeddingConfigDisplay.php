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

final class EmbeddingConfigDisplay extends AbstractServiceConfigDisplay {

	private const SETTINGS_GROUP = 'service-embedding';
	private const CONNECTION_GROUP = 'connection';
	private const SERVICE_TYPE = 'embedding';

	public static function getName(): string {
		return 'embeddingconfigdisplay';
	}

	public function getHelp(): string {
		return 'Configure embedding services stored in settings group "service-embedding".';
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
		return 'Display/EmbeddingConfigDisplay.php';
	}

	protected function getInstancePrefix(): string {
		return 'embeddingcfg';
	}

	protected function getListDataKey(): string {
		return 'embeddings';
	}

	protected function getSingleDataKey(): string {
		return 'embedding';
	}

	protected function getMissingIdMessage(): string {
		return 'Missing embedding id.';
	}

	protected function getMissingNameMessage(): string {
		return 'Missing embedding name.';
	}

	protected function getMissingDriverMessage(): string {
		return 'Missing service driver.';
	}

	protected function getUnknownDriverMessage(string $driver): string {
		return 'Unknown embedding service driver: ' . $driver;
	}

	protected function getMissingModelMessage(): string {
		return 'Missing model.';
	}

	protected function readSpecificOptions(array $options): array {
		$dimensions = $this->readOptionalInt('dimensions', 'Dimensions');
		$batchSize = $this->readOptionalInt('batchSize', 'Batch size');
		$normalizeVectors = $this->normalizeBool($this->request->request('normalizeVectors', 0));
		$timeoutSeconds = $this->readOptionalInt('timeoutSeconds', 'Timeout seconds');
		$connectTimeoutSeconds = $this->readOptionalInt('connectTimeoutSeconds', 'Connect timeout seconds');

		if($dimensions !== null) {
			$options['dimensions'] = $dimensions;
		}

		if($batchSize !== null) {
			$options['batchSize'] = $batchSize;
		}

		$options['normalizeVectors'] = $normalizeVectors;

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

		$row['dimensions'] = $this->normalizeNullableNumber($options['dimensions'] ?? null);
		$row['batchSize'] = $this->normalizeNullableNumber($options['batchSize'] ?? null);
		$row['normalizeVectors'] = $this->normalizeBool($options['normalizeVectors'] ?? false);
		$row['timeoutSeconds'] = $this->normalizeNullableNumber($options['timeoutSeconds'] ?? null);
		$row['connectTimeoutSeconds'] = $this->normalizeNullableNumber($options['connectTimeoutSeconds'] ?? null);

		return $row;
	}
}
