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

use AssistantFoundation\Api\IServiceDriverDefinition;
use MissionBay\Api\ISpeechToTextDriver;
use MissionBay\Speech\MistralSpeechToTextDriver;

final class MistralSpeechToTextDriverDefinition implements IServiceDriverDefinition {

	public static function getName(): string {
		return 'mistralspeechtotextdriverdefinition';
	}

	public function getDriver(): string {
		return 'mistral-stt';
	}

	public function getServiceType(): string {
		return 'stt';
	}

	public function getLabel(): string {
		return 'Mistral Speech-to-Text';
	}

	public function requiresConnection(): bool {
		return true;
	}

	public function getSupportedConnectionTypes(): array {
		return ['http'];
	}

	public function getImplementationInterface(): string {
		return ISpeechToTextDriver::class;
	}

	public function getImplementationName(): string {
		return MistralSpeechToTextDriver::getName();
	}

	public function getConfigSchema(): array {
		return [
			'type' => 'object',
			'properties' => [
				'model' => [
					'type' => 'string',
					'label' => 'Transcription model',
					'default' => 'voxtral-mini-latest',
					'required' => true
				],
				'realtimeModel' => [
					'type' => 'string',
					'label' => 'Realtime transcription model',
					'default' => 'voxtral-mini-transcribe-realtime-2602'
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
					'default' => 1200
				],
				'chunkDurationMs' => [
					'type' => 'integer',
					'label' => 'Audio chunk duration (ms)',
					'default' => 480
				],
				'noSpeechTimeoutMs' => [
					'type' => 'integer',
					'label' => 'No-speech timeout (ms)',
					'default' => 10000
				],
				'diarize' => [
					'type' => 'boolean',
					'label' => 'Diarize complete transcriptions',
					'default' => false
				]
			]
		];
	}

	public function getDefaultConfig(): array {
		return [
			'serviceType' => 'stt',
			'driver' => 'mistral-stt',
			'model' => 'voxtral-mini-latest',
			'enabled' => true,
			'options' => [
				'realtimeModel' => 'voxtral-mini-transcribe-realtime-2602',
				'language' => 'de',
				'sampleRate' => 16000,
				'targetStreamingDelayMs' => 480,
				'silenceDurationMs' => 1200,
				'chunkDurationMs' => 480,
				'finalizationTimeoutMs' => 10000,
				'noSpeechTimeoutMs' => 10000,
				'diarize' => false
			]
		];
	}
}
