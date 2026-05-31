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

final class ImageConfigDisplay extends AbstractServiceConfigDisplay {

	private const SETTINGS_GROUP = 'service-image';
	private const CONNECTION_GROUP = 'connection';
	private const SERVICE_TYPE = 'image';

	public static function getName(): string {
		return 'imageconfigdisplay';
	}

	public function getHelp(): string {
		return 'Configure image generation services stored in settings group "service-image".';
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
		return 'Display/ImageConfigDisplay.php';
	}

	protected function getInstancePrefix(): string {
		return 'imagecfg';
	}

	protected function getListDataKey(): string {
		return 'images';
	}

	protected function getSingleDataKey(): string {
		return 'image';
	}

	protected function getMissingIdMessage(): string {
		return 'Missing image service id.';
	}

	protected function getMissingNameMessage(): string {
		return 'Missing image service name.';
	}

	protected function getMissingDriverMessage(): string {
		return 'Missing service driver.';
	}

	protected function getUnknownDriverMessage(string $driver): string {
		return 'Unknown image service driver: ' . $driver;
	}

	protected function getMissingModelMessage(): string {
		return 'Missing model.';
	}

	protected function readSpecificOptions(array $options): array {
		$numberOfImages = $this->readOptionalInt('numberOfImages', 'Number of images');
		$outputCompression = $this->readOptionalInt('outputCompression', 'Output compression');
		$timeoutSeconds = $this->readOptionalInt('timeoutSeconds', 'Timeout seconds');
		$connectTimeoutSeconds = $this->readOptionalInt('connectTimeoutSeconds', 'Connect timeout seconds');
		$size = trim((string)$this->request->request('size', ''));
		$quality = trim((string)$this->request->request('quality', ''));
		$outputFormat = trim((string)$this->request->request('outputFormat', ''));
		$background = trim((string)$this->request->request('background', ''));
		$moderation = trim((string)$this->request->request('moderation', ''));

		if($size !== '') {
			$options['size'] = $size;
		}

		if($quality !== '') {
			$options['quality'] = $quality;
		}

		if($outputFormat !== '') {
			$options['outputFormat'] = $outputFormat;
		}

		if($background !== '') {
			$options['background'] = $background;
		}

		if($moderation !== '') {
			$options['moderation'] = $moderation;
		}

		if($numberOfImages !== null) {
			$options['numberOfImages'] = $numberOfImages;
		}

		if($outputCompression !== null) {
			$options['outputCompression'] = $outputCompression;
		}

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

		$row['size'] = trim((string)($options['size'] ?? ''));
		$row['quality'] = trim((string)($options['quality'] ?? ''));
		$row['outputFormat'] = trim((string)($options['outputFormat'] ?? ''));
		$row['background'] = trim((string)($options['background'] ?? ''));
		$row['moderation'] = trim((string)($options['moderation'] ?? ''));
		$row['numberOfImages'] = $this->normalizeNullableNumber($options['numberOfImages'] ?? null);
		$row['outputCompression'] = $this->normalizeNullableNumber($options['outputCompression'] ?? null);
		$row['timeoutSeconds'] = $this->normalizeNullableNumber($options['timeoutSeconds'] ?? null);
		$row['connectTimeoutSeconds'] = $this->normalizeNullableNumber($options['connectTimeoutSeconds'] ?? null);

		return $row;
	}
}
