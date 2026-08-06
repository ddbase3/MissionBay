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

use RuntimeException;

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
		$driver = $this->normalizeKey((string)$this->request->request('driver', ''));
		$drivers = $this->listDriverDefinitionsByDriver();
		$definition = $drivers[$driver] ?? null;

		if(!is_array($definition)) {
			return $options;
		}

		$schema = is_array($definition['configSchema'] ?? null) ? $definition['configSchema'] : [];
		$properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
		$requestData = $this->request->allRequest();

		foreach($properties as $key => $property) {
			if(!is_string($key) || $key === 'model' || !is_array($property)) {
				continue;
			}

			if(!array_key_exists($key, $requestData)) {
				continue;
			}

			$value = $this->readSchemaValue($key, $property);

			if($value === null) {
				unset($options[$key]);
				continue;
			}

			$options[$key] = $value;
		}

		return $options;
	}

	protected function expandSpecificDisplayOptions(array $row): array {
		return $row;
	}

	/**
	 * @param array<string,mixed> $property
	 */
	private function readSchemaValue(string $key, array $property): mixed {
		$type = strtolower(trim((string)($property['type'] ?? 'string')));
		$label = trim((string)($property['label'] ?? $key));
		$required = (bool)($property['required'] ?? false);
		$raw = $this->request->request($key, null);

		if($type === 'boolean') {
			if($raw === null || $raw === '') {
				return $required ? false : null;
			}

			return $this->normalizeBool($raw);
		}

		if($raw === null) {
			if($required) {
				throw new RuntimeException($label . ' is required.');
			}

			return null;
		}

		if($type === 'integer' || $type === 'number') {
			$value = trim((string)$raw);

			if($value === '') {
				if($required) {
					throw new RuntimeException($label . ' is required.');
				}

				return null;
			}

			if(!is_numeric($value)) {
				throw new RuntimeException($label . ' must be numeric.');
			}

			$number = $type === 'integer' ? (int)$value : (float)$value;

			if(isset($property['minimum']) && is_numeric($property['minimum']) && $number < (float)$property['minimum']) {
				throw new RuntimeException($label . ' must be at least ' . $property['minimum'] . '.');
			}

			if(isset($property['maximum']) && is_numeric($property['maximum']) && $number > (float)$property['maximum']) {
				throw new RuntimeException($label . ' must be at most ' . $property['maximum'] . '.');
			}

			return $number;
		}

		$value = trim((string)$raw);

		if($value === '') {
			if($required) {
				throw new RuntimeException($label . ' is required.');
			}

			return null;
		}

		$enum = is_array($property['enum'] ?? null) ? $property['enum'] : [];

		if($enum !== [] && !in_array($value, $enum, true)) {
			throw new RuntimeException($label . ' contains an unsupported value.');
		}

		return $value;
	}
}
