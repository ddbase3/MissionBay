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

namespace MissionBay\ServiceDriver;

use MissionBay\Api\IServiceDriverDefinition;

final class MistralRealtimeSpeechToTextDriverDefinition implements IServiceDriverDefinition {

	public static function getName(): string {
		return 'mistralrealtimespeechtotextdriverdefinition';
	}

	public function getDriver(): string {
		return 'mistral-realtime-stt';
	}

	public function getServiceType(): string {
		return 'stt';
	}

	public function getLabel(): string {
		return 'Mistral Realtime Speech-to-Text';
	}

	public function requiresConnection(): bool {
		return true;
	}

	public function getSupportedConnectionTypes(): array {
		return ['http'];
	}

	public function getConfigSchema(): array {
		return [
			'type' => 'object',
			'properties' => [
				'model' => [
					'type' => 'string',
					'label' => 'Model',
					'default' => 'voxtral-mini-transcribe-realtime-2602',
					'required' => true
				],
				'language' => [
					'type' => 'string',
					'label' => 'Language',
					'default' => 'de'
				],
				'sampleRate' => [
					'type' => 'integer',
					'label' => 'Sample rate',
					'default' => 16000
				],
				'targetStreamingDelayMs' => [
					'type' => 'integer',
					'label' => 'Target streaming delay (ms)',
					'default' => 480
				],
				'silenceDurationMs' => [
					'type' => 'integer',
					'label' => 'Silence before stop (ms)',
					'default' => 900
				],
				'noSpeechTimeoutMs' => [
					'type' => 'integer',
					'label' => 'No-speech timeout (ms)',
					'default' => 10000
				]
			]
		];
	}

	public function getDefaultConfig(): array {
		return [
			'serviceType' => 'stt',
			'driver' => 'mistral-realtime-stt',
			'model' => 'voxtral-mini-transcribe-realtime-2602',
			'enabled' => true,
			'options' => [
				'mode' => 'realtime',
				'language' => 'de',
				'sampleRate' => 16000,
				'targetStreamingDelayMs' => 480,
				'silenceDurationMs' => 900,
				'noSpeechTimeoutMs' => 10000,
				'interimResults' => true
			]
		];
	}
}
