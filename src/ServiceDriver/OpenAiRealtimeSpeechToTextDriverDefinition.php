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
use MissionBay\Api\IRealtimeSpeechToTextDriver;
use MissionBay\Speech\OpenAiRealtimeSpeechToTextDriver;

final class OpenAiRealtimeSpeechToTextDriverDefinition implements IServiceDriverDefinition {

	public static function getName(): string {
		return 'openairealtimespeechtotextdriverdefinition';
	}

	public function getDriver(): string {
		return 'openai-realtime-stt';
	}

	public function getServiceType(): string {
		return 'stt';
	}

	public function getLabel(): string {
		return 'OpenAI Realtime Speech-to-Text';
	}

	public function requiresConnection(): bool {
		return true;
	}

	public function getSupportedConnectionTypes(): array {
		return ['http'];
	}

	public function getImplementationInterface(): string {
		return IRealtimeSpeechToTextDriver::class;
	}

	public function getImplementationName(): string {
		return OpenAiRealtimeSpeechToTextDriver::getName();
	}

	public function getConfigSchema(): array {
		return [
			'type' => 'object',
			'properties' => [
				'model' => [
					'type' => 'string',
					'label' => 'Model',
					'default' => 'gpt-4o-mini-transcribe',
					'required' => true
				],
				'language' => [
					'type' => 'string',
					'label' => 'Language',
					'default' => 'de'
				],
				'prompt' => [
					'type' => 'string',
					'label' => 'Prompt',
					'default' => ''
				],
				'vadThreshold' => [
					'type' => 'number',
					'label' => 'VAD threshold',
					'minimum' => 0,
					'maximum' => 1,
					'default' => 0.5
				],
				'prefixPaddingMs' => [
					'type' => 'integer',
					'label' => 'Prefix padding (ms)',
					'default' => 300
				],
				'silenceDurationMs' => [
					'type' => 'integer',
					'label' => 'Silence before stop (ms)',
					'default' => 800
				],
				'noiseReduction' => [
					'type' => 'string',
					'label' => 'Noise reduction',
					'enum' => ['near_field', 'far_field', 'off'],
					'default' => 'near_field'
				]
			]
		];
	}

	public function getDefaultConfig(): array {
		return [
			'serviceType' => 'stt',
			'driver' => 'openai-realtime-stt',
			'model' => 'gpt-4o-mini-transcribe',
			'enabled' => true,
			'options' => [
				'mode' => 'realtime',
				'language' => 'de',
				'prompt' => '',
				'vadThreshold' => 0.5,
				'prefixPaddingMs' => 300,
				'silenceDurationMs' => 800,
				'noiseReduction' => 'near_field',
				'chunkDurationMs' => 100,
				'finalizationTimeoutMs' => 10000,
				'interimResults' => true
			]
		];
	}
}
