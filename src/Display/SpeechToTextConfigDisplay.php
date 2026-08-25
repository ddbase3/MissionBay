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

final class SpeechToTextConfigDisplay extends AbstractServiceConfigDisplay {

	private const SETTINGS_GROUP = 'service-stt';
	private const CONNECTION_GROUP = 'connection';
	private const SERVICE_TYPE = 'stt';

	private const DEDICATED_OPTION_KEYS = [
		'realtimeModel',
		'language',
		'languages',
		'sampleRate',
		'targetStreamingDelayMs',
		'silenceDurationMs',
		'noSpeechTimeoutMs',
		'vadThreshold',
		'prefixPaddingMs',
		'diarize',
		'keywords',
		'vocabulary',
		'delay',
		'noiseReduction',
		'clientSecretTtlSeconds',
		'prompt',
		'fastStreamingDelayMs',
		'slowStreamingDelayMs',
		'chunkDurationMs',
		'sessionTimeoutMs',
		'finalizationTimeoutMs',
		'mode',
		'interimResults'
	];

	public static function getName(): string {
		return 'speechtotextconfigdisplay';
	}

	public function getHelp(): string {
		return 'Configure realtime speech-to-text services stored in settings group "service-stt".';
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
		foreach(self::DEDICATED_OPTION_KEYS as $key) {
			unset($options[$key]);
		}

		$driver = $this->normalizeKey((string)$this->request->request('driver', ''));
		$requiredWords = $this->readStringList('requiredWords');
		$finalizationTimeoutMs = $this->readOptionalInt('finalizationTimeoutMs', 'Finalization timeout');

		if($driver === 'openai-stt') {
			$languages = $this->readStringList('languages');
			$delay = strtolower(trim((string)$this->request->request('delay', '')));
			$noiseReduction = strtolower(trim((string)$this->request->request('noiseReduction', '')));
			$clientSecretTtlSeconds = $this->readOptionalInt('clientSecretTtlSeconds', 'Client secret TTL');
			$prompt = trim((string)$this->request->request('prompt', ''));

			if($delay !== '' && !in_array($delay, ['low', 'medium', 'high'], true)) {
				throw new RuntimeException('Transcription delay must be low, medium or high.');
			}
			if($noiseReduction !== '' && !in_array($noiseReduction, ['near_field', 'far_field'], true)) {
				throw new RuntimeException('Noise reduction must be near_field or far_field.');
			}

			$this->setOptional($options, 'languages', $languages);
			$this->setOptional($options, 'keywords', $requiredWords);
			$this->setOptional($options, 'delay', $delay);
			$this->setOptional($options, 'noiseReduction', $noiseReduction);
			$this->setOptional($options, 'clientSecretTtlSeconds', $clientSecretTtlSeconds);
			$this->setOptional($options, 'prompt', $prompt);
			$this->setOptional($options, 'finalizationTimeoutMs', $finalizationTimeoutMs);
		}
		elseif($driver === 'mistral-stt') {
			$this->setOptional($options, 'vocabulary', $requiredWords);
			$this->setOptional(
				$options,
				'fastStreamingDelayMs',
				$this->readOptionalInt('fastStreamingDelayMs', 'Fast stream delay')
			);
			$this->setOptional(
				$options,
				'slowStreamingDelayMs',
				$this->readOptionalInt('slowStreamingDelayMs', 'Correction stream delay')
			);
			$this->setOptional(
				$options,
				'chunkDurationMs',
				$this->readOptionalInt('chunkDurationMs', 'Audio chunk duration')
			);
			$this->setOptional(
				$options,
				'sessionTimeoutMs',
				$this->readOptionalInt('sessionTimeoutMs', 'Session initialization timeout')
			);
			$this->setOptional($options, 'finalizationTimeoutMs', $finalizationTimeoutMs);
		}

		return $options;
	}

	protected function expandSpecificDisplayOptions(array $row): array {
		$options = is_array($row['options'] ?? null) ? $row['options'] : [];
		$driver = $this->normalizeKey((string)($row['driver'] ?? ''));
		$requiredWords = $driver === 'openai-stt'
			? $this->normalizeStringList($options['keywords'] ?? [])
			: $this->normalizeStringList($options['vocabulary'] ?? []);

		$row['languages'] = implode("\n", $this->normalizeStringList($options['languages'] ?? []));
		$row['requiredWords'] = implode("\n", $requiredWords);
		$row['delay'] = trim((string)($options['delay'] ?? ''));
		$row['noiseReduction'] = trim((string)($options['noiseReduction'] ?? ''));
		$row['clientSecretTtlSeconds'] = $this->normalizeNullableNumber($options['clientSecretTtlSeconds'] ?? null);
		$row['prompt'] = trim((string)($options['prompt'] ?? ''));
		$row['fastStreamingDelayMs'] = $this->normalizeNullableNumber($options['fastStreamingDelayMs'] ?? null);
		$row['slowStreamingDelayMs'] = $this->normalizeNullableNumber($options['slowStreamingDelayMs'] ?? null);
		$row['chunkDurationMs'] = $this->normalizeNullableNumber($options['chunkDurationMs'] ?? null);
		$row['sessionTimeoutMs'] = $this->normalizeNullableNumber($options['sessionTimeoutMs'] ?? null);
		$row['finalizationTimeoutMs'] = $this->normalizeNullableNumber($options['finalizationTimeoutMs'] ?? null);
		$row['advancedOptions'] = $this->removeDedicatedOptions($options);

		return $row;
	}

	/** @return array<int,string> */
	private function readStringList(string $key): array {
		return $this->normalizeStringList($this->request->request($key, ''));
	}

	/** @return array<int,string> */
	private function normalizeStringList(mixed $value): array {
		if(is_string($value)) {
			$value = preg_split('/[\r\n,]+/', $value) ?: [];
		}
		if(!is_array($value)) {
			return [];
		}

		$result = [];
		foreach($value as $item) {
			if(!is_scalar($item)) {
				continue;
			}
			$item = trim((string)$item);
			if($item === '') {
				continue;
			}
			$result[] = $item;
		}

		return array_values(array_unique($result));
	}

	private function setOptional(array &$options, string $key, mixed $value): void {
		if($value === null || $value === '' || $value === []) {
			unset($options[$key]);
			return;
		}

		$options[$key] = $value;
	}

	/** @param array<string,mixed> $options @return array<string,mixed> */
	private function removeDedicatedOptions(array $options): array {
		foreach(self::DEDICATED_OPTION_KEYS as $key) {
			unset($options[$key]);
		}

		return $options;
	}
}
