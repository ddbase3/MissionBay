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

final class TextToSpeechConfigDisplay extends AbstractServiceConfigDisplay {

	private const SETTINGS_GROUP = 'service-tts';
	private const CONNECTION_GROUP = 'connection';
	private const SERVICE_TYPE = 'tts';

	public static function getName(): string {
		return 'texttospeechconfigdisplay';
	}

	public function getHelp(): string {
		return 'Configure text-to-speech services stored in settings group "service-tts".';
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
		return 'Display/TextToSpeechConfigDisplay.php';
	}

	protected function getInstancePrefix(): string {
		return 'ttscfg';
	}

	protected function getListDataKey(): string {
		return 'textToSpeechServices';
	}

	protected function getSingleDataKey(): string {
		return 'textToSpeechService';
	}

	protected function getMissingIdMessage(): string {
		return 'Missing text-to-speech service id.';
	}

	protected function getMissingNameMessage(): string {
		return 'Missing text-to-speech service name.';
	}

	protected function getMissingDriverMessage(): string {
		return 'Missing text-to-speech driver.';
	}

	protected function getUnknownDriverMessage(string $driver): string {
		return 'Unknown text-to-speech driver: ' . $driver;
	}

	protected function getMissingModelMessage(): string {
		return 'Missing text-to-speech model.';
	}

	protected function readSpecificOptions(array $options): array {
		$voice = trim((string)$this->request->request('voice', ''));
		$responseFormat = strtolower(trim((string)$this->request->request('responseFormat', 'mp3')));
		$speed = $this->readOptionalFloat('speed', 'Speed');
		$instructions = trim((string)$this->request->request('instructions', ''));

		if($voice === '') {
			throw new RuntimeException('Missing text-to-speech voice.');
		}
		if(!in_array($responseFormat, ['mp3', 'opus', 'aac', 'flac', 'wav'], true)) {
			throw new RuntimeException('Unsupported text-to-speech response format.');
		}
		if($speed !== null && ($speed < 0.25 || $speed > 4.0)) {
			throw new RuntimeException('Text-to-speech speed must be between 0.25 and 4.0.');
		}

		$options['voice'] = $voice;
		$options['responseFormat'] = $responseFormat;
		$options['speed'] = $speed ?? 1.0;
		$options['instructions'] = $instructions;

		return $options;
	}

	protected function expandSpecificDisplayOptions(array $row): array {
		$options = is_array($row['options'] ?? null) ? $row['options'] : [];
		$row['voice'] = trim((string)($options['voice'] ?? ''));
		$row['responseFormat'] = strtolower(trim((string)($options['responseFormat'] ?? 'mp3')));
		$row['speed'] = $this->normalizeNullableNumber($options['speed'] ?? 1.0);
		$row['instructions'] = trim((string)($options['instructions'] ?? ''));
		return $row;
	}
}
