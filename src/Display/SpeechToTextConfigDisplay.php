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

final class SpeechToTextConfigDisplay extends AbstractServiceConfigDisplay {

	private const SETTINGS_GROUP = 'service-stt';
	private const CONNECTION_GROUP = 'connection';
	private const SERVICE_TYPE = 'stt';

	public static function getName(): string {
		return 'speechtotextconfigdisplay';
	}

	public function getHelp(): string {
		return 'Configure speech-to-text services stored in settings group "service-stt".';
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
		return 'Display/SpeechToTextConfigDisplay.php';
	}

	protected function getInstancePrefix(): string {
		return 'sttcfg';
	}

	protected function getListDataKey(): string {
		return 'speechToTextServices';
	}

	protected function getSingleDataKey(): string {
		return 'speechToTextService';
	}

	protected function getMissingIdMessage(): string {
		return 'Missing speech-to-text service id.';
	}

	protected function getMissingNameMessage(): string {
		return 'Missing speech-to-text service name.';
	}

	protected function getMissingDriverMessage(): string {
		return 'Missing speech-to-text driver.';
	}

	protected function getUnknownDriverMessage(string $driver): string {
		return 'Unknown speech-to-text driver: ' . $driver;
	}

	protected function getMissingModelMessage(): string {
		return 'Missing speech-to-text model.';
	}

	protected function readSpecificOptions(array $options): array {
		$language = trim((string)$this->request->request('language', ''));
		$sampleRate = $this->readOptionalInt('sampleRate', 'Sample rate');
		$targetStreamingDelayMs = $this->readOptionalInt('targetStreamingDelayMs', 'Target streaming delay');
		$silenceDurationMs = $this->readOptionalInt('silenceDurationMs', 'Silence duration');
		$noSpeechTimeoutMs = $this->readOptionalInt('noSpeechTimeoutMs', 'No-speech timeout');

		$options['mode'] = 'realtime';
		$options['interimResults'] = true;

		if($language !== '') {
			$options['language'] = $language;
		}
		if($sampleRate !== null) {
			$options['sampleRate'] = $sampleRate;
		}
		if($targetStreamingDelayMs !== null) {
			$options['targetStreamingDelayMs'] = $targetStreamingDelayMs;
		}
		if($silenceDurationMs !== null) {
			$options['silenceDurationMs'] = $silenceDurationMs;
		}
		if($noSpeechTimeoutMs !== null) {
			$options['noSpeechTimeoutMs'] = $noSpeechTimeoutMs;
		}

		return $options;
	}

	protected function expandSpecificDisplayOptions(array $row): array {
		$options = is_array($row['options'] ?? null) ? $row['options'] : [];
		$row['mode'] = 'realtime';
		$row['language'] = trim((string)($options['language'] ?? ''));
		$row['sampleRate'] = $this->normalizeNullableNumber($options['sampleRate'] ?? null);
		$row['targetStreamingDelayMs'] = $this->normalizeNullableNumber($options['targetStreamingDelayMs'] ?? null);
		$row['silenceDurationMs'] = $this->normalizeNullableNumber($options['silenceDurationMs'] ?? null);
		$row['noSpeechTimeoutMs'] = $this->normalizeNullableNumber($options['noSpeechTimeoutMs'] ?? null);
		return $row;
	}
}
