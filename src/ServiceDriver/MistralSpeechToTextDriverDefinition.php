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
		return 'Mistral Dual-Stream Realtime Speech-to-Text';
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
					'label' => 'Realtime transcription model',
					'default' => 'voxtral-mini-transcribe-realtime-2602',
					'required' => true
				],
				'vocabulary' => [
					'type' => 'array',
					'label' => 'Required and corrected words',
					'default' => ['ILIAS']
				],
				'fastStreamingDelayMs' => [
					'type' => 'integer',
					'label' => 'Fast stream delay (ms)',
					'default' => 240
				],
				'slowStreamingDelayMs' => [
					'type' => 'integer',
					'label' => 'Correction stream delay (ms)',
					'default' => 2400
				],
				'chunkDurationMs' => [
					'type' => 'integer',
					'label' => 'Audio chunk duration (ms)',
					'default' => 20
				],
				'sessionTimeoutMs' => [
					'type' => 'integer',
					'label' => 'Session initialization timeout (ms)',
					'default' => 12000
				],
				'finalizationTimeoutMs' => [
					'type' => 'integer',
					'label' => 'Finalization timeout (ms)',
					'default' => 25000
				]
			]
		];
	}

	public function getDefaultConfig(): array {
		return [
			'serviceType' => 'stt',
			'driver' => 'mistral-stt',
			'model' => 'voxtral-mini-transcribe-realtime-2602',
			'enabled' => true,
			'options' => [
				'vocabulary' => ['ILIAS'],
				'fastStreamingDelayMs' => 240,
				'slowStreamingDelayMs' => 2400,
				'chunkDurationMs' => 20,
				'sessionTimeoutMs' => 12000,
				'finalizationTimeoutMs' => 25000
			]
		];
	}
}
